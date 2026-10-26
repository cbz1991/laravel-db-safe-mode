<?php

namespace Cabuz\SafeMode\Guards;

use Illuminate\Database\MySqlConnection;

class GuardedMySqlConnection extends MySqlConnection
{
    use GuardsWrites;
}
