<?php namespace CRSCompany\Backster\Models;

use Model;

/**
 * Model
 */
class Backup extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var bool timestamps are disabled.
     * Remove this line if timestamps are defined in the database table.
     */
    public $timestamps = false;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'crscompany_backster_backups';

    /**
     * @var array rules for validation.
     */
    public $rules = [
    ];

}
