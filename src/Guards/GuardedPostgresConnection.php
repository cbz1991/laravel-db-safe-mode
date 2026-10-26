<?php

namespace Cabuz\SafeMode\Guards;

use Illuminate\Database\PostgresConnection;

class GuardedPostgresConnection extends PostgresConnection
{
    use GuardsWrites;
}
