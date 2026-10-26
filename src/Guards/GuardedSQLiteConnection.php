<?php

namespace Cabuz\SafeMode\Guards;

use Illuminate\Database\SQLiteConnection;

class GuardedSQLiteConnection extends SQLiteConnection
{
    use GuardsWrites;
}
