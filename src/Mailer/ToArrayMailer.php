<?php declare(strict_types = 1);

namespace OriNette\Mail\Mailer;

use Nette\Mail\Mailer;
use Nette\Mail\Message;

final class ToArrayMailer implements Mailer
{

	/** @var list<Message> */
	private array $messages = [];

	public function send(Message $mail): void
	{
		$this->messages[] = clone $mail;
	}

	/**
	 * @return list<Message>
	 */
	public function getMessages(): array
	{
		return $this->messages;
	}

}
