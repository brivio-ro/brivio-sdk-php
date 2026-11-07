<?php

declare(strict_types=1);

namespace Brivio\Tests\Support;

/**
 * Minimal OpenAPI 3.1 / JSON Schema validator for request bodies, read from
 * the published contract docs/brivio-openapi.json (the spec the SDKs are
 * generated against, see scripts/openapi-sync.mjs).
 *
 * Supports what the Brivio request schemas use: $ref, type (incl. type
 * arrays and `nullable`), required, properties, additionalProperties,
 * items, enum, minLength/maxLength, minimum/maximum/exclusiveMinimum,
 * minItems and the `date` / `uuid` / `email` formats.
 *
 * `$closed = true` treats every object WITHOUT an explicit
 * `additionalProperties` as closed, so a key the contract does not declare is
 * an error. The gateway's zod schemas silently strip unknown keys, which is
 * exactly how the old legacy bodies (`client{}`, `lines[]`, …) passed the
 * transport and then failed on a missing required field — strict mode makes
 * that class of drift visible in the test.
 */
final class OpenApiSchema
{
    /** @var array<string,mixed> */
    private array $spec;

    private function __construct(array $spec)
    {
        $this->spec = $spec;
    }

    public static function specPath(): string
    {
        return dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'brivio-openapi.json';
    }

    public static function load(): ?self
    {
        $path = self::specPath();
        if (!is_file($path)) {
            return null;
        }
        /** @var array<string,mixed> $spec */
        $spec = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        return new self($spec);
    }

    /** @return array<string,mixed> request-body schema of `$method $path` */
    public function requestSchema(string $method, string $path): array
    {
        $op = $this->spec['paths'][$path][strtolower($method)] ?? null;
        if (!is_array($op)) {
            throw new \LogicException("{$method} {$path} is not in the OpenAPI spec");
        }
        $schema = $op['requestBody']['content']['application/json']['schema'] ?? null;
        if (!is_array($schema)) {
            throw new \LogicException("{$method} {$path} has no JSON request body in the OpenAPI spec");
        }
        return $this->resolve($schema);
    }

    /**
     * @param array<string,mixed> $schema
     * @return list<string> errors; empty = valid
     */
    public function validate(mixed $value, array $schema, bool $closed = false, string $at = '$'): array
    {
        $schema = $this->resolve($schema);
        $errors = [];

        $types = $schema['type'] ?? null;
        if ($types !== null) {
            $types = is_array($types) ? $types : [$types];
            if (($schema['nullable'] ?? false) === true) {
                $types[] = 'null';
            }
            if (!self::matchesAnyType($value, $types)) {
                return ["{$at}: expected " . implode('|', $types) . ', got ' . get_debug_type($value)];
            }
        }
        if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = "{$at}: " . json_encode($value) . ' not in enum ' . json_encode($schema['enum']);
        }

        if (is_string($value)) {
            $errors = [...$errors, ...self::stringErrors($value, $schema, $at)];
        } elseif (is_int($value) || is_float($value)) {
            $errors = [...$errors, ...self::numberErrors($value, $schema, $at)];
        } elseif (is_array($value) && array_is_list($value) && ($value !== [] || self::declares($schema, 'array'))) {
            if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
                $errors[] = "{$at}: fewer than {$schema['minItems']} items";
            }
            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($value as $i => $item) {
                    $errors = [...$errors, ...$this->validate($item, $schema['items'], $closed, "{$at}[{$i}]")];
                }
            }
        } elseif (is_array($value)) {
            $errors = [...$errors, ...$this->objectErrors($value, $schema, $closed, $at)];
        }
        return $errors;
    }

    /**
     * @param array<mixed> $value
     * @param array<string,mixed> $schema
     * @return list<string>
     */
    private function objectErrors(array $value, array $schema, bool $closed, string $at): array
    {
        $errors = [];
        /** @var array<string,array<string,mixed>> $props */
        $props = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        foreach ((array) ($schema['required'] ?? []) as $req) {
            if (!array_key_exists($req, $value)) {
                $errors[] = "{$at}: missing required property '{$req}'";
            }
        }
        $additional = $schema['additionalProperties'] ?? ($closed && $props !== [] ? false : true);
        foreach ($value as $key => $v) {
            $key = (string) $key;
            if (isset($props[$key])) {
                $errors = [...$errors, ...$this->validate($v, $props[$key], $closed, "{$at}.{$key}")];
            } elseif ($additional === false) {
                $errors[] = "{$at}: unknown property '{$key}'";
            } elseif (is_array($additional)) {
                $errors = [...$errors, ...$this->validate($v, $additional, $closed, "{$at}.{$key}")];
            }
        }
        return $errors;
    }

    /**
     * @param array<string,mixed> $schema
     * @return list<string>
     */
    private static function stringErrors(string $value, array $schema, string $at): array
    {
        $errors = [];
        $len = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if (isset($schema['minLength']) && $len < $schema['minLength']) {
            $errors[] = "{$at}: shorter than {$schema['minLength']}";
        }
        if (isset($schema['maxLength']) && $len > $schema['maxLength']) {
            $errors[] = "{$at}: longer than {$schema['maxLength']}";
        }
        $format = $schema['format'] ?? null;
        $ok = match ($format) {
            'date' => preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value) === 1,
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            default => true,
        };
        if (!$ok) {
            $errors[] = "{$at}: '{$value}' is not a valid {$format}";
        }
        return $errors;
    }

    /**
     * @param array<string,mixed> $schema
     * @return list<string>
     */
    private static function numberErrors(int|float $value, array $schema, string $at): array
    {
        $errors = [];
        if (isset($schema['minimum']) && $value < $schema['minimum']) {
            $errors[] = "{$at}: below minimum {$schema['minimum']}";
        }
        if (isset($schema['maximum']) && $value > $schema['maximum']) {
            $errors[] = "{$at}: above maximum {$schema['maximum']}";
        }
        if (isset($schema['exclusiveMinimum']) && is_numeric($schema['exclusiveMinimum']) && $value <= $schema['exclusiveMinimum']) {
            $errors[] = "{$at}: must be > {$schema['exclusiveMinimum']}";
        }
        return $errors;
    }

    /** @param list<mixed> $types */
    private static function matchesAnyType(mixed $value, array $types): bool
    {
        foreach ($types as $t) {
            $ok = match ($t) {
                'string' => is_string($value),
                'number' => is_int($value) || is_float($value),
                'integer' => is_int($value) || (is_float($value) && floor($value) === $value),
                'boolean' => is_bool($value),
                'null' => $value === null,
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ($value === [] || !array_is_list($value)),
                default => true,
            };
            if ($ok) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $schema */
    private static function declares(array $schema, string $type): bool
    {
        $t = $schema['type'] ?? null;
        return $t === $type || (is_array($t) && in_array($type, $t, true));
    }

    /**
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private function resolve(array $schema): array
    {
        $guard = 0;
        while (isset($schema['$ref']) && is_string($schema['$ref'])) {
            if (++$guard > 32 || !str_starts_with($schema['$ref'], '#/')) {
                throw new \LogicException('unresolvable $ref ' . $schema['$ref']);
            }
            $node = $this->spec;
            foreach (explode('/', substr($schema['$ref'], 2)) as $part) {
                $part = str_replace(['~1', '~0'], ['/', '~'], $part);
                if (!is_array($node) || !array_key_exists($part, $node)) {
                    throw new \LogicException('dangling $ref ' . $schema['$ref']);
                }
                $node = $node[$part];
            }
            if (!is_array($node)) {
                throw new \LogicException('$ref to a non-schema ' . $schema['$ref']);
            }
            $schema = $node;
        }
        return $schema;
    }
}
