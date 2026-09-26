<?php

namespace App\Exceptions;

/**
 * A refund transfer was refused before any money moved, for a reason the admin
 * needs to read: no destination on file, a blocked institution, a zero amount,
 * test-mode keys, an insufficient wallet balance, or a transfer that is already
 * in flight. Every one of those messages ends by naming the way out ("send it
 * by hand and record it with Mark Paid Out"), so it is written to be shown.
 *
 * WHY THIS CLASS EXISTS RATHER THAN A `catch (\RuntimeException)`. The
 * controller used to catch `\Throwable` and echo `getMessage()` verbatim, on
 * the stated reasoning that everything `send()` throws is a pre-flight guard
 * that is safe to display. That is true of the guards and false of the catch:
 * it also covered the database writes and the HTTP call.
 *
 * Narrowing to `\RuntimeException` would NOT have fixed it, which is the whole
 * point of a dedicated class here:
 *
 *     Illuminate\Database\QueryException
 *         <- PDOException  <- RuntimeException  <- Exception
 *
 * A QueryException is a RuntimeException, and its message carries the SQL along
 * with its bound values. This project has been caught by that inheritance once
 * already — in Task 8, where a broad `catch (RuntimeException)` silently
 * swallowed a fixture error and made a test pass for no reason.
 *
 * So the type has to be one nothing else throws by accident. Anything that is
 * NOT this class is an internal fault: it gets reported and the admin gets a
 * generic message.
 */
class RefundNotSendable extends \RuntimeException {}
