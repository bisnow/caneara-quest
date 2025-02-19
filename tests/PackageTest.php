<?php

declare(strict_types=1);

namespace Quest\Tests;

use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Quest\ServiceProvider;

class PackageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $setup = [
            'driver'         => 'mysql',
            'url'            => env('DATABASE_URL'),
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', 3306),
            'database'       => env('DB_DATABASE', 'quest'),
            'username'       => env('DB_USERNAME', 'root'),
            'password'       => env('DB_PASSWORD', ''),
            'unix_socket'    => env('DB_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_520_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => null,
        ];

        app()['config']->set('database.default', 'mysql');
        app()['config']->set('database.connections.mysql', $setup);

        (new ServiceProvider(app()))->boot();

        $this->loadMigrationsFrom(__DIR__ . '/../support/migrations');

        DB::table('users')->truncate();

        DB::table('users')->insert(['name' => 'John Doe', 'nickname' => 'jndoe', 'country' => 'United States']);
        DB::table('users')->insert(['name' => 'Jane Doe', 'nickname' => 'jndoe', 'country' => 'United Kingdom']);
        DB::table('users')->insert(['name' => 'Fred Doe', 'nickname' => 'fredrick', 'country' => 'France']);
        DB::table('users')->insert(['name' => 'William Doe', 'nickname' => 'willy', 'country' => 'Italy']);
    }

    #[Test]
    /** @test */
    public function it_can_perform_a_fuzzy_search_and_receive_one_result(): void
    {
        $results = DB::table('users')
            ->whereFuzzy('users.name', 'jad')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Jane Doe', $results->first()->name);
    }

    #[Test]
    /** @test */
    public function it_can_perform_a_fuzzy_search_and_receive_multiple_results(): void
    {
        $results = DB::table('users')
            ->whereFuzzy('name', 'jd')
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Doe', $results[1]->name);
    }

    #[Test]
    /** @test */
    public function it_can_perform_a_fuzzy_search_and_paginate_multiple_results(): void
    {
        $results = DB::table('users')
            ->whereFuzzy('name', 'jd')
            ->simplePaginate(1, ['*'], 'page', 1);

        $this->assertEquals('John Doe', $results->items()[0]->name);

        $results = DB::table('users')
            ->whereFuzzy('name', 'jd')
            ->simplePaginate(1, ['*'], 'page', 2);

        $this->assertEquals('Jane Doe', $results->items()[0]->name);
    }

    #[Test]
    /** @test */
    public function it_can_perform_a_fuzzy_search_across_multiple_fields(): void
    {
        $results = DB::table('users')
            ->whereFuzzy('name', 'jd')
            ->whereFuzzy('country', 'uk')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Jane Doe', $results[0]->name);
    }

    #[Test]
    /** @test */
    public function it_can_order_a_fuzzy_search_by_one_field(): void
    {
        $results = DB::table('users')
            ->whereFuzzy('name', 'jd')
            ->whereFuzzy('country', 'un')
            ->orderByFuzzy('country')
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Doe', $results[1]->name);
    }

    #[Test]
    /** @test */
    public function it_can_order_a_fuzzy_search_by_multiple_fields(): void
    {
        $results = DB::table('users')
            ->whereFuzzy('name', 'jd')
            ->whereFuzzy('country', 'un')
            ->orderByFuzzy(['name', 'country'])
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals('Jane Doe', $results[1]->name);
    }

    #[Test]
    /** @test */
    public function it_can_perform_an_eloquent_fuzzy_search(): void
    {
        $results = User::whereFuzzy('name', 'jad')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Jane Doe', $results->first()->name);
    }

    #[Test]
    /** @test */
    public function it_can_perform_an_eloquent_fuzzy_or_search(): void
    {
        $results = User::whereFuzzy(function ($query): void {
            $query->orWhereFuzzy('name', 'jndoe');
            $query->orWhereFuzzy('nickname', 'jndoe');
        })
            ->get();

        $this->assertEquals('John Doe', $results->first()->name);
    }

    #[Test]
    /** @test */
    public function it_can_perform_an_eloquent_fuzzy_or_search_with_order(): void
    {
        $results = User::whereFuzzy(function ($query): void {
            $query->orWhereFuzzy('name', 'jad');
            $query->orWhereFuzzy('nickname', 'jndoe');
        })
            ->orderByFuzzy('name')
            ->get();

        $this->assertEquals('Jane Doe', $results->first()->name);
    }

    #[Test]
    /** @test */
    public function it_can_perform_an_eloquent_fuzzy_or_search_with_relevance(): void
    {
        $results = User::whereFuzzy(function ($query): void {
            $query->orWhereFuzzy('name', 'ed', 30); // Jane Doe has a relevance of 11
            $query->orWhereFuzzy('country', 'Italy', 10);
        })
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals('William Doe', $results[0]->name);
        $this->assertEquals('Fred Doe', $results[1]->name);
    }

    #[Test]
    /** @test */
    public function it_can_perform_an_eloquent_fuzzy_and_search_with_fuzzy_order(): void
    {
        $results = User::whereFuzzy(function ($query): void {
            $query->whereFuzzy('name', 'jad');
            $query->whereFuzzy('nickname', 'jndoe');
        })
            ->orderByFuzzy('name')
            ->get();

        $this->assertEquals('Jane Doe', $results->first()->name);
    }

    #[Test]
    /** @test */
    public function it_can_limit_minimum_score(): void
    {
        $results = User::whereFuzzy('name', 'joh Do')
            ->withMinimumRelevance(65)
            ->get();

        $this->assertEquals('John Doe', $results->first()->name);

        $results = User::whereFuzzy('name', 'joh Do')
            ->withMinimumRelevance(70)
            ->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    /** @test */
    public function it_can_perform_an_eloquent_fuzzy_and_search_with_enabled_fuzzy_order_having_clause(): void
    {
        $results = User::whereFuzzy(function ($query): void {
            $query->whereFuzzy('name', 'jad', true);
            $query->whereFuzzy('name', 'William Doe', true);
        });

        $this->assertStringContainsString('order by', $results->toSql());
    }

    #[Test]
    /** @test */
    public function it_can_perform_an_eloquent_fuzzy_and_search_with_disabled_fuzzy_order_having_clause(): void
    {
        $results = User::whereFuzzy(function ($query): void {
            $query->whereFuzzy('name', 'jad', false);
            $query->whereFuzzy('name', 'wp', false);
        });

        $this->assertStringNotContainsString('order by', $results->toSql());
    }

    #[Test]
    /** @test */
    public function it_can_disable_matchers(): void
    {
        $results = User::whereFuzzy(function ($query): void {
            $query->whereFuzzy('name', 'jad', true, [
                'StudlyCaseMatcher',
            ]);
        });

        $this->assertStringNotContainsString("LIKE BINARY 'J%A%D%', 32, 0)", $results->toSql());
    }

    #[Test]
    /** @test */
    public function it_does_not_disable_matchers(): void
    {
        $results = User::whereFuzzy(function ($query): void {
            $query->whereFuzzy('name', 'jad', true);
        });

        $this->assertStringContainsString("LIKE BINARY 'J%A%D%', 32, 0)", $results->toSql());
    }
}
