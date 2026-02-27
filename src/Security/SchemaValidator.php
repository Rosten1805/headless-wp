<?php
/**
 * Validador de body POST/PUT contra un subconjunto de JSON Schema Draft 2020-12.
 *
 * @package HWP\Security
 */

namespace HWP\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SchemaValidator
 *
 * Implementa un subconjunto de JSON Schema suficiente para validar cuerpos
 * de request en endpoints dinámicos sin depender de librerías externas.
 *
 * Palabras clave soportadas:
 *   - type            (string, integer, number, boolean, array, object, null)
 *   - required        (array de nombres de propiedad requeridos)
 *   - properties      (validación recursiva de propiedades de un objeto)
 *   - additionalProperties (false = rechaza campos no declarados en properties)
 *   - enum            (el valor debe estar en la lista)
 *   - minimum / maximum   (para tipo numérico)
 *   - minLength / maxLength (para tipo string)
 *   - minItems / maxItems   (para tipo array)
 *   - items           (schema aplicado a cada elemento del array)
 *   - nullable        (permite null independientemente del type declarado)
 *
 * Retorna una lista de errores con la estructura:
 *   [ 'field' => 'path.to.field', 'message' => '...', 'code' => 'hwp_*' ]
 */
final class SchemaValidator {

	// ─────────────────────────────────────────────
	// Punto de entrada público
	// ─────────────────────────────────────────────

	/**
	 * Valida $data contra $schema.
	 *
	 * @param array<string,mixed> $data   Datos decodificados del body del request.
	 * @param array<string,mixed> $schema Schema en formato array (JSON Schema subset).
	 * @return array<int,array{field:string,message:string,code:string}>
	 *         Lista de errores. Vacía si la validación pasa.
	 */
	public function validate( array $data, array $schema ): array {
		return $this->validateValue( $data, $schema, '' );
	}

	/**
	 * Retorna true si $data es válido, false si hay al menos un error.
	 *
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $schema
	 */
	public function isValid( array $data, array $schema ): bool {
		return empty( $this->validate( $data, $schema ) );
	}

	// ─────────────────────────────────────────────
	// Validación recursiva interna
	// ─────────────────────────────────────────────

	/**
	 * @param mixed               $value
	 * @param array<string,mixed> $schema
	 * @param string              $path   Ruta en dot-notation para el mensaje de error.
	 * @return array<int,array{field:string,message:string,code:string}>
	 */
	private function validateValue( mixed $value, array $schema, string $path ): array {
		$errors = [];

		// nullable shortcut: si el valor es null y el schema lo permite, pasa.
		if ( null === $value && ( $schema['nullable'] ?? false ) ) {
			return [];
		}

		// type
		if ( isset( $schema['type'] ) ) {
			$typeErrors = $this->validateType( $value, $schema['type'], $path );
			if ( ! empty( $typeErrors ) ) {
				return $typeErrors; // sin sentido continuar con un tipo incorrecto
			}
		}

		// enum
		if ( isset( $schema['enum'] ) ) {
			$errors = array_merge( $errors, $this->validateEnum( $value, $schema['enum'], $path ) );
		}

		// Restricciones numéricas
		if ( isset( $schema['minimum'] ) && is_numeric( $value ) ) {
			if ( $value < $schema['minimum'] ) {
				$errors[] = $this->error(
					$path,
					sprintf( 'Value must be >= %s', $schema['minimum'] ),
					'hwp_validation_error'
				);
			}
		}

		if ( isset( $schema['maximum'] ) && is_numeric( $value ) ) {
			if ( $value > $schema['maximum'] ) {
				$errors[] = $this->error(
					$path,
					sprintf( 'Value must be <= %s', $schema['maximum'] ),
					'hwp_validation_error'
				);
			}
		}

		// Restricciones de string
		if ( is_string( $value ) ) {
			if ( isset( $schema['minLength'] ) && mb_strlen( $value ) < $schema['minLength'] ) {
				$errors[] = $this->error(
					$path,
					sprintf( 'String must be at least %d character(s)', $schema['minLength'] ),
					'hwp_validation_error'
				);
			}

			if ( isset( $schema['maxLength'] ) && mb_strlen( $value ) > $schema['maxLength'] ) {
				$errors[] = $this->error(
					$path,
					sprintf( 'String must not exceed %d character(s)', $schema['maxLength'] ),
					'hwp_validation_error'
				);
			}
		}

		// Restricciones de array
		if ( is_array( $value ) && ! $this->isAssoc( $value ) ) {
			if ( isset( $schema['minItems'] ) && count( $value ) < $schema['minItems'] ) {
				$errors[] = $this->error(
					$path,
					sprintf( 'Array must contain at least %d item(s)', $schema['minItems'] ),
					'hwp_validation_error'
				);
			}

			if ( isset( $schema['maxItems'] ) && count( $value ) > $schema['maxItems'] ) {
				$errors[] = $this->error(
					$path,
					sprintf( 'Array must not contain more than %d item(s)', $schema['maxItems'] ),
					'hwp_validation_error'
				);
			}

			if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
				foreach ( $value as $idx => $item ) {
					$itemPath  = $path !== '' ? "{$path}[{$idx}]" : "[{$idx}]";
					$errors    = array_merge( $errors, $this->validateValue( $item, $schema['items'], $itemPath ) );
				}
			}
		}

		// Validación de objeto (properties, required, additionalProperties)
		// Un array vacío [] se trata como objeto cuando el schema declara type:object,
		// ya que json_decode('{}') produce [] en PHP.
		if ( is_array( $value ) ) {
			$schemaType   = $schema['type'] ?? null;
			$isObjectType = $schemaType === 'object'
				|| ( is_array( $schemaType ) && in_array( 'object', $schemaType, true ) );

			if ( $this->isAssoc( $value ) || $isObjectType ) {
				$errors = array_merge( $errors, $this->validateObject( $value, $schema, $path ) );
			}
		}

		return $errors;
	}

	/**
	 * Valida el tipo del valor.
	 *
	 * @param mixed        $value
	 * @param string|array $type   Tipo o array de tipos permitidos.
	 * @param string       $path
	 * @return array<int,array{field:string,message:string,code:string}>
	 */
	private function validateType( mixed $value, string|array $type, string $path ): array {
		$types   = (array) $type;
		$matched = false;

		foreach ( $types as $t ) {
			if ( $this->matchesType( $value, $t ) ) {
				$matched = true;
				break;
			}
		}

		if ( ! $matched ) {
			$typesStr = implode( '|', $types );
			$actual   = $this->phpTypeLabel( $value );
			return [
				$this->error(
					$path,
					sprintf( "Expected type '%s', got '%s'", $typesStr, $actual ),
					'hwp_validation_error'
				),
			];
		}

		return [];
	}

	/**
	 * Comprueba si $value coincide con el tipo JSON Schema $type.
	 */
	private function matchesType( mixed $value, string $type ): bool {
		return match ( $type ) {
			'null'    => null === $value,
			'boolean' => is_bool( $value ),
			'integer' => is_int( $value ),
			'number'  => is_int( $value ) || is_float( $value ),
			'string'  => is_string( $value ),
			// Un array vacío puede ser tanto [] como {} en JSON — se acepta en ambos tipos.
			'array'   => is_array( $value ) && ! $this->isAssoc( $value ),
			'object'  => is_array( $value ) && ( $this->isAssoc( $value ) || empty( $value ) ),
			default   => false,
		};
	}

	/**
	 * Valida enum.
	 *
	 * @param array<mixed> $allowed
	 * @return array<int,array{field:string,message:string,code:string}>
	 */
	private function validateEnum( mixed $value, array $allowed, string $path ): array {
		if ( ! in_array( $value, $allowed, true ) ) {
			$allowedStr = implode( ', ', array_map(
				static fn ( $v ) => null === $v ? 'null' : (string) $v,
				$allowed
			) );
			return [
				$this->error(
					$path,
					sprintf( "Value must be one of: %s", $allowedStr ),
					'hwp_validation_error'
				),
			];
		}

		return [];
	}

	/**
	 * Valida properties, required y additionalProperties de un objeto.
	 *
	 * @param array<string,mixed> $object
	 * @param array<string,mixed> $schema
	 * @param string              $path
	 * @return array<int,array{field:string,message:string,code:string}>
	 */
	private function validateObject( array $object, array $schema, string $path ): array {
		$errors     = [];
		$properties = $schema['properties'] ?? [];

		// required
		foreach ( $schema['required'] ?? [] as $requiredField ) {
			if ( ! array_key_exists( $requiredField, $object ) ) {
				$fieldPath = $path !== '' ? "{$path}.{$requiredField}" : $requiredField;
				$errors[]  = $this->error(
					$fieldPath,
					sprintf( "Field '%s' is required", $requiredField ),
					'hwp_validation_error'
				);
			}
		}

		// properties (validación recursiva)
		foreach ( $properties as $propName => $propSchema ) {
			if ( array_key_exists( $propName, $object ) ) {
				$fieldPath = $path !== '' ? "{$path}.{$propName}" : $propName;
				$errors    = array_merge(
					$errors,
					$this->validateValue( $object[ $propName ], $propSchema, $fieldPath )
				);
			}
		}

		// additionalProperties: false
		if ( isset( $schema['additionalProperties'] ) && false === $schema['additionalProperties'] ) {
			$declaredKeys = array_keys( $properties );
			foreach ( array_keys( $object ) as $key ) {
				if ( ! in_array( $key, $declaredKeys, true ) ) {
					$fieldPath = $path !== '' ? "{$path}.{$key}" : $key;
					$errors[]  = $this->error(
						$fieldPath,
						sprintf( "Additional property '%s' is not allowed", $key ),
						'hwp_validation_error'
					);
				}
			}
		}

		return $errors;
	}

	// ─────────────────────────────────────────────
	// Helpers privados
	// ─────────────────────────────────────────────

	/**
	 * Construye un item de error estandarizado.
	 *
	 * @return array{field:string,message:string,code:string}
	 */
	private function error( string $field, string $message, string $code ): array {
		return [
			'field'   => $field,
			'message' => $message,
			'code'    => $code,
		];
	}

	/**
	 * Devuelve true si el array es asociativo (objeto JSON) y no secuencial (array JSON).
	 */
	private function isAssoc( array $array ): bool {
		if ( empty( $array ) ) {
			return false;
		}
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}

	/**
	 * Etiqueta legible del tipo PHP de un valor, para mensajes de error.
	 */
	private function phpTypeLabel( mixed $value ): string {
		if ( null === $value ) {
			return 'null';
		}
		if ( is_bool( $value ) ) {
			return 'boolean';
		}
		if ( is_int( $value ) ) {
			return 'integer';
		}
		if ( is_float( $value ) ) {
			return 'number';
		}
		if ( is_string( $value ) ) {
			return 'string';
		}
		if ( is_array( $value ) ) {
			return $this->isAssoc( $value ) ? 'object' : 'array';
		}
		return gettype( $value );
	}
}
