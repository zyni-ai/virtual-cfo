<?php

use App\Enums\ImportStatus;
use App\Enums\MappingType;

expect()->extend('toBeCompleted', function () {
    return $this->status->toBe(ImportStatus::Completed);
});

expect()->extend('toBeFailed', function () {
    return $this->status->toBe(ImportStatus::Failed);
});

expect()->extend('toBeMapped', function () {
    return $this->mapping_type->not->toBe(MappingType::Unmapped);
});

expect()->extend('toBeUnmapped', function () {
    return $this->mapping_type->toBe(MappingType::Unmapped);
});
