<?php

namespace Cabuz\SafeMode\Guards;

use Illuminate\Database\MariaDbConnection;

class GuardedMariaDbConnection extends MariaDbConnection
{
    use GuardsWrites;
}
