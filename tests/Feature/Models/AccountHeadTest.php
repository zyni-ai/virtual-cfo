<?php

use App\Models\AccountHead;

describe('AccountHead::fullPath', function () {
    it('returns just the name when there is no parent', function () {
        $head = AccountHead::factory()->create(['name' => 'Bank Accounts']);

        expect($head->full_path)->toBe('Bank Accounts');
    });

    it('builds hierarchical path with one parent', function () {
        $parent = AccountHead::factory()->create(['name' => 'Current Assets']);
        $child = AccountHead::factory()->withParent($parent)->create(['name' => 'Bank Accounts']);

        expect($child->full_path)->toBe('Current Assets > Bank Accounts');
    });

    it('builds hierarchical path with multiple levels', function () {
        $grandparent = AccountHead::factory()->create(['name' => 'Assets']);
        $parent = AccountHead::factory()->withParent($grandparent)->create(['name' => 'Current Assets']);
        $child = AccountHead::factory()->withParent($parent)->create(['name' => 'Bank Accounts']);

        expect($child->full_path)->toBe('Assets > Current Assets > Bank Accounts');
    });
});

describe('AccountHead relationships', function () {
    it('has children', function () {
        $parent = AccountHead::factory()->create();
        $children = AccountHead::factory()->withParent($parent)->count(3)->create();

        expect($parent->children)->toHaveCount(3);
    });

    it('has transactions', function () {
        $head = AccountHead::factory()->create();

        expect($head->transactions)->toBeEmpty();
    });

    it('has head mappings', function () {
        $head = AccountHead::factory()->create();

        expect($head->headMappings)->toBeEmpty();
    });
});
