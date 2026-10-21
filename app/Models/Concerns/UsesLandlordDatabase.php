<?php

namespace App\Models\Concerns;

use App\Support\Tenancy;

/**
 * Platform-wide records (ZamPayroll billing, support) always live in the
 * central database, even while a request runs inside a private workspace.
 */
trait UsesLandlordDatabase
{
    public function getConnectionName()
    {
        return Tenancy::landlordConnection();
    }
}
