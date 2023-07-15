<?php declare(strict_types = 1);

namespace OriNette\Mail\Mailer;

use Nette\Mail\Mailer;
use Nette\Mail\Message;
use function is_string;

final class OverwriteRecipientMailer implements Mailer
{

	private Mailer $mailer;

	/** @var array<string, string|null> */
	private array $to;

	/** @var array<string, string|null> */
	private array $cc;

	/** @var array<string, string|null> */
	private array $bcc;

	/**
	 * @param string|array<string, string|null> $to
	 * @param array<string, string|null> $cc
	 * @param array<string, string|null> $bcc
	 */
	public function __construct(
		Mailer $mailer,
		$to,
		array $cc = [],
		array $bcc = []
	)
	{
		if (is_string($to)) {
			$to = [$to => null];
		}

		$this->mailer = $mailer;
		$this->to = $to;
		$this->cc = $cc;
		$this->bcc = $bcc;
	}

	public function send(Message $mail): void
	{
		$changedMail = clone $mail;

		$this->backupOriginalHeader($changedMail, 'To');
		$this->backupOriginalHeader($changedMail, 'Cc');
		$this->backupOriginalHeader($changedMail, 'Bcc');

		foreach ($this->to as $email => $name) {
			$changedMail->addTo($email, $name);
		}

		foreach ($this->cc as $email => $name) {
			$changedMail->addCc($email, $name);
		}

		foreach ($this->bcc as $email => $name) {
			$changedMail->addBcc($email, $name);
		}

		$this->mailer->send($changedMail);
	}

	private function backupOriginalHeader(Message $mail, string $header): void
	{
		$value = $mail->getHeader($header);

		if ($value === null) {
			return;
		}

		$mail->clearHeader($header);
		$mail->setHeader("X-Original-$header", $value);
	}

}
