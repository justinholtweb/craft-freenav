<?php

namespace justinholt\freenav\models;

use craft\base\Model;

/**
 * What a menu import did, or why it did nothing.
 *
 * An import is all-or-nothing: when `$errors` is non-empty no menu was created and no node
 * was written or removed. `$warnings` are things that imported but may not be what you meant,
 * such as an entry link whose entry doesn't exist in this environment.
 */
class ImportResult extends Model
{
    public ?Menu $menu = null;
    public bool $createdMenu = false;
    public int $nodesImported = 0;
    public int $nodesRemoved = 0;

    /** @var string[] */
    public array $errors = [];

    /** @var string[] */
    public array $warnings = [];

    public function getSuccess(): bool
    {
        return $this->errors === [];
    }
}
