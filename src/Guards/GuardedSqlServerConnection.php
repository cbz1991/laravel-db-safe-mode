<?php

namespace Cabuz\SafeMode\Guards;

use Illuminate\Database\SqlServerConnection;

class GuardedSqlServerConnection extends SqlServerConnection
{
    use GuardsWrites;
}
