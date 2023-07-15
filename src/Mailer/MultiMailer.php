<?php declare(strict_types = 1);

namespace OriNette\Mail\Mailer;

use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Orisai\Exceptions\Logic\InvalidState;
use Throwable;
use function array_pop;
use function count;

final class MultiMailer implements Mailer
{

	/** @var list<Mailer> */
	private array $mailers;

	/**
	 * @param list<Mailer> $mailers
	 */
	public function __construct(array $mailers)
	{
		$this->mailers = $mailers;
	}

	public function send(Message $mail): void
	{
		$failures = [];

		foreach ($this->mailers as $mailer) {
			try {
				$mailer->send($mail);
			} catch (Throwable $failure) {
				$failures[] = $failure;
			}
		}

		if ($failures !== []) {
			if (count($failures) === 1) {
				throw array_pop($failures);
			}

			throw InvalidState::create()
				->withMessage('Some of the mailers failed to send the message.')
				->withSuppressed($failures);
		}
	}

}
