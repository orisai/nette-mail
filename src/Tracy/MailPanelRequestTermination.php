<?php declare(strict_types = 1);

namespace OriNette\Mail\Tracy;

use Orisai\Exceptions\LogicalException;

/**
 * @internal
 */
final class MailPanelRequestTermination extends LogicalException
{

	public static function create(): self
	{
		return new self();
	}

}
