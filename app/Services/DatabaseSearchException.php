<?php

namespace App\Services;

/**
 * A database API refused a search (missing or invalid key, invalid query, paging limit);
 * the message is shown to the user.
 */
class DatabaseSearchException extends \RuntimeException
{
}
