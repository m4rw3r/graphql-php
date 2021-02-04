<?php

declare(strict_types=1);

namespace GraphQL\Tests\Regression;

use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

class Issue771Test extends TestCase
{
    public function testInterfaceLazyTypeLoader()
    {
        $a = new InterfaceType([
            'name' => 'A',
            'fields' => ['name' => Type::string()],
            'resolveType' => static function ($src) use (&$b, &$c) : ?Type {
                switch ($src) {
                    case 'B':
                        return $b;
                    case 'C':
                        return $c;
                    default:
                        return null;
                }
            },
        ]);
        $b = new ObjectType(['name' => 'B', 'fields' => ['name' => Type::string()], 'interfaces' => [$a]]);
        $c = new ObjectType(['name' => 'C', 'fields' => ['name' => Type::string()], 'interfaces' => [$a]]);

        $exampleType = new ObjectType([
            'name' => 'Example',
            'fields' => [
                'field' => [
                    'type' => $a,
                    'resolve' => static function () {
                        return 'B';
                    },
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => $exampleType,
            // Uncommenting this line makes it work
            // 'types' => [$a, $b, $c],
            'typeLoader' => static function (string $name) use (&$a, &$b, &$c) : ?Type {
                switch ($name) {
                    case 'A':
                        return $a;
                    case 'B':
                        return $b;
                    case 'C':
                        return $c;
                    default:
                        return null;
                }
            },
        ]);

        $query = '
            query {
                field {
                    __typename
                    name
                }
            }
        ';

        $expected = [
            'data' => [
                'field' => [
                    '__typename' => 'B',
                    'name' => null,
                ],
            ],
        ];

        $result = GraphQL::executeQuery($schema, $query);

        self::assertEquals(
            $expected,
            $result->toArray(
                DebugFlag::RETHROW_INTERNAL_EXCEPTIONS
            )
        );
    }
}
