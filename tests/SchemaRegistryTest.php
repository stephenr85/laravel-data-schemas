<?php

namespace Rushing\LaravelDataSchemas\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rushing\LaravelDataSchemas\Lifecycle\FilesystemSchemaRegistry;
use Rushing\LaravelDataSchemas\Lifecycle\SchemaRegistryConflict;

class SchemaRegistryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/lds-registry-'.uniqid();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function test_it_registers_and_resolves_a_schema_by_id(): void
    {
        $registry = new FilesystemSchemaRegistry($this->dir);
        $schema = [
            '$id' => 'https://schemas.splicewire.app/content/article/3',
            'type' => 'object',
            'properties' => ['title' => ['type' => 'string']],
        ];

        $registry->register($schema);

        $this->assertTrue($registry->has($schema['$id']));
        $this->assertSame($schema, $registry->get($schema['$id']));
        $this->assertSame([$schema['$id']], $registry->ids());
    }

    public function test_it_resolves_a_nested_addressable_node_by_its_own_id(): void
    {
        $registry = new FilesystemSchemaRegistry($this->dir);
        $author = [
            '$id' => 'https://schemas.splicewire.app/content/author/2',
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
        ];

        $registry->register($author);

        $this->assertNotNull($registry->get($author['$id']));
    }

    public function test_re_registering_an_identical_shape_is_an_idempotent_no_op(): void
    {
        $registry = new FilesystemSchemaRegistry($this->dir);
        $schema = ['$id' => 'https://x.test/a/1', 'type' => 'object', 'properties' => ['x' => ['type' => 'string']]];

        $registry->register($schema);
        // Re-publishing the same shape (even with reworded description) is allowed.
        $registry->register($schema + ['description' => 'reworded']);

        $this->assertTrue($registry->has($schema['$id']));
    }

    public function test_overwriting_a_frozen_id_with_a_different_fingerprint_is_rejected(): void
    {
        $registry = new FilesystemSchemaRegistry($this->dir);
        $id = 'https://x.test/a/1';

        $registry->register(['$id' => $id, 'type' => 'object', 'properties' => ['x' => ['type' => 'string']]]);

        $this->expectException(SchemaRegistryConflict::class);
        $registry->register(['$id' => $id, 'type' => 'object', 'properties' => ['x' => ['type' => 'integer']]]);
    }

    public function test_registering_without_an_id_throws(): void
    {
        $registry = new FilesystemSchemaRegistry($this->dir);

        $this->expectException(InvalidArgumentException::class);
        $registry->register(['type' => 'object']);
    }
}
