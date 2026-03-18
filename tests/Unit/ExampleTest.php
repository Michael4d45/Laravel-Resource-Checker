<?php

declare(strict_types=1);

test('command is registered', function (): void {
    $this->artisan('list')
        ->expectsOutputToContain('check:migrations-resources');
});
