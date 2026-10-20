<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * A tiny, safe arithmetic expression evaluator for derived ("calculated") fields.
 *
 * Supports numbers, variables (field keys), the operators + - * / % and ^,
 * parentheses, unary minus, and the functions min, max, round, abs, ceil, floor
 * and if. There is no use of eval(); the input is tokenised and parsed.
 */
class FormulaEvaluator
{
    /** @var array<int, array{type: string, value: mixed}> */
    private array $tokens = [];

    private int $pos = 0;

    /**
     * Evaluate an expression against a map of variable values.
     *
     * @param  array<string, mixed>  $variables
     */
    public function evaluate(string $expression, array $variables = []): float
    {
        $this->tokens = $this->tokenize($expression);
        $this->pos = 0;

        $value = $this->parseComparison($variables);

        if ($this->pos < count($this->tokens)) {
            throw new InvalidArgumentException('Unexpected token near position ' . $this->pos . '.');
        }

        return (float) $value;
    }

    /**
     * Validate an expression for syntax errors and collect referenced variables.
     *
     * @return array<int, string> the variable names referenced by the expression
     */
    public function referencedVariables(string $expression): array
    {
        $vars = [];
        foreach ($this->tokenize($expression) as $token) {
            if ($token['type'] === 'var') {
                $vars[$token['value']] = true;
            }
        }

        // Also ensure the expression actually parses.
        $this->evaluate($expression, array_fill_keys(array_keys($vars), 0));

        return array_keys($vars);
    }

    /** Public helper so callers can validate syntax without providing variables. */
    public function assertValid(string $expression): void
    {
        $this->referencedVariables($expression);
    }

    /**
     * @return array<int, array{type: string, value: mixed}>
     */
    private function tokenize(string $expression): array
    {
        if (trim($expression) === '') {
            throw new InvalidArgumentException('The formula is empty.');
        }

        $tokens = [];
        $length = strlen($expression);
        $i = 0;

        while ($i < $length) {
            $char = $expression[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if (ctype_digit($char) || $char === '.') {
                $num = '';
                while ($i < $length && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                    $num .= $expression[$i];
                    $i++;
                }
                if (! is_numeric($num)) {
                    throw new InvalidArgumentException("Invalid number '{$num}'.");
                }
                $tokens[] = ['type' => 'num', 'value' => (float) $num];
                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $name = '';
                while ($i < $length && (ctype_alnum($expression[$i]) || $expression[$i] === '_')) {
                    $name .= $expression[$i];
                    $i++;
                }
                $tokens[] = ['type' => 'var', 'value' => $name];
                continue;
            }

            if (str_contains('+-*/%^(),', $char)) {
                $tokens[] = ['type' => $char, 'value' => $char];
                $i++;
                continue;
            }

            if (str_contains('<>!=', $char)) {
                $op = $char;
                $i++;
                if ($i < $length && $expression[$i] === '=' && str_contains('<>!', $op)) {
                    $op .= '=';
                    $i++;
                } elseif ($op === '=' && $i < $length && $expression[$i] === '=') {
                    $op = '==';
                    $i++;
                }
                $tokens[] = ['type' => $op, 'value' => $op];
                continue;
            }

            throw new InvalidArgumentException("Unexpected character '{$char}'.");
        }

        return $tokens;
    }

    /**
     * Comparisons bind looser than + / - so `a + b > c` works.
     *
     * @param  array<string, mixed>  $variables
     */
    private function parseComparison(array $variables): float
    {
        $left = $this->parseExpression($variables);

        $comparisons = ['>', '<', '>=', '<=', '==', '!='];

        while (in_array($this->peekType(), $comparisons, true)) {
            $op = $this->next()['type'];
            $right = $this->parseExpression($variables);

            $result = match ($op) {
                '>' => $left > $right,
                '<' => $left < $right,
                '>=' => $left >= $right,
                '<=' => $left <= $right,
                '==' => $left == $right,
                default => $left != $right,
            };

            $left = $result ? 1.0 : 0.0;
        }

        return $left;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function parseExpression(array $variables): float
    {
        $left = $this->parseTerm($variables);

        while ($this->peekType() === '+' || $this->peekType() === '-') {
            $op = $this->next()['type'];
            $right = $this->parseTerm($variables);
            $left = $op === '+' ? $left + $right : $left - $right;
        }

        return $left;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function parseTerm(array $variables): float
    {
        $left = $this->parsePower($variables);

        while (in_array($this->peekType(), ['*', '/', '%'], true)) {
            $op = $this->next()['type'];
            $right = $this->parsePower($variables);

            if ($op === '/') {
                if ($right == 0.0) {
                    throw new InvalidArgumentException('Division by zero.');
                }
                $left = $left / $right;
            } elseif ($op === '%') {
                if ($right == 0.0) {
                    throw new InvalidArgumentException('Modulo by zero.');
                }
                $left = fmod($left, $right);
            } else {
                $left = $left * $right;
            }
        }

        return $left;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function parsePower(array $variables): float
    {
        $base = $this->parseUnary($variables);

        if ($this->peekType() === '^') {
            $this->next();
            $exp = $this->parsePower($variables);

            return $base ** $exp;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function parseUnary(array $variables): float
    {
        $type = $this->peekType();

        if ($type === '-') {
            $this->next();

            return -$this->parseUnary($variables);
        }

        if ($type === '+') {
            $this->next();

            return $this->parseUnary($variables);
        }

        return $this->parsePrimary($variables);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function parsePrimary(array $variables): float
    {
        $token = $this->peek();

        if ($token === null) {
            throw new InvalidArgumentException('Unexpected end of formula.');
        }

        if ($token['type'] === 'num') {
            $this->next();

            return (float) $token['value'];
        }

        if ($token['type'] === '(') {
            $this->next();
            $value = $this->parseComparison($variables);
            if ($this->peekType() !== ')') {
                throw new InvalidArgumentException('Missing closing parenthesis.');
            }
            $this->next();

            return $value;
        }

        if ($token['type'] === 'var') {
            $this->next();
            $name = $token['value'];

            if ($this->peekType() === '(') {
                return $this->parseFunction($name, $variables);
            }

            if (! array_key_exists($name, $variables)) {
                throw new InvalidArgumentException("Unknown value '{$name}' in formula.");
            }

            return $this->toNumber($variables[$name], $name);
        }

        throw new InvalidArgumentException('Unexpected token in formula.');
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function parseFunction(string $name, array $variables): float
    {
        $this->next(); // consume '('

        // if() is lazy: only the chosen branch is evaluated, so guard
        // expressions like if(x>0, y/x, 0) are safe against division by zero.
        if (strtolower($name) === 'if') {
            $condition = $this->parseComparison($variables);

            if ($condition != 0.0) {
                $value = $this->parseComparison($variables);
                $this->skipRemainingArgs();
            } else {
                $this->skipOneArgument();
                $this->next(); // consume ','
                $value = $this->parseComparison($variables);
                $this->skipRemainingArgs();
            }

            if ($this->peekType() !== ')') {
                throw new InvalidArgumentException("Missing closing parenthesis for '{$name}'.");
            }
            $this->next();

            return $value;
        }

        $args = [];

        if ($this->peekType() !== ')') {
            $args[] = $this->parseComparison($variables);
            while ($this->peekType() === ',') {
                $this->next();
                $args[] = $this->parseComparison($variables);
            }
        }

        if ($this->peekType() !== ')') {
            throw new InvalidArgumentException("Missing closing parenthesis for '{$name}'.");
        }
        $this->next();

        return $this->applyFunction($name, $args);
    }

    /** Skip one function argument's tokens (stops before ',' or ')' at depth 0). */
    private function skipOneArgument(): void
    {
        $depth = 0;
        $count = count($this->tokens);

        while ($this->pos < $count) {
            $type = $this->peekType();

            if ($depth === 0 && ($type === ',' || $type === ')')) {
                return;
            }

            if ($type === '(') {
                $depth++;
            } elseif ($type === ')') {
                $depth--;
            }

            $this->pos++;
        }
    }

    /** Skip any remaining arguments (stops before the closing ')' at depth 0). */
    private function skipRemainingArgs(): void
    {
        $depth = 0;
        $count = count($this->tokens);

        while ($this->pos < $count) {
            $type = $this->peekType();

            if ($depth === 0 && $type === ')') {
                return;
            }

            if ($type === '(') {
                $depth++;
            } elseif ($type === ')') {
                $depth--;
            }

            $this->pos++;
        }
    }

    /**
     * @param  array<int, float>  $args
     */
    private function applyFunction(string $name, array $args): float
    {
        return match (strtolower($name)) {
            'min' => $args === [] ? 0.0 : min($args),
            'max' => $args === [] ? 0.0 : max($args),
            'sum' => array_sum($args),
            'round' => round($args[0] ?? 0.0, (int) ($args[1] ?? 0)),
            'abs' => abs($args[0] ?? 0.0),
            'ceil' => ceil($args[0] ?? 0.0),
            'floor' => floor($args[0] ?? 0.0),
            default => throw new InvalidArgumentException("Unknown function '{$name}'."),
        };
    }

    private function toNumber(mixed $value, string $name): float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if ($value === null || $value === '') {
            return 0.0;
        }

        throw new InvalidArgumentException("Value '{$name}' is not numeric.");
    }

    /**
     * @return array{type: string, value: mixed}|null
     */
    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function peekType(): ?string
    {
        return $this->tokens[$this->pos]['type'] ?? null;
    }

    /**
     * @return array{type: string, value: mixed}
     */
    private function next(): array
    {
        return $this->tokens[$this->pos++];
    }
}
