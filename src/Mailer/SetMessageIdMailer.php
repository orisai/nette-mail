<?php declare(strict_types = 1);

namespace OriNette\Mail\Mailer;

use Nette\Http\IRequest;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Utils\Random;
use function assert;
use function is_string;
use function php_uname;
use function preg_replace;

final class SetMessageIdMailer implements Mailer
{

	private Mailer $mailer;

	private ?IRequest $request;

	public function __construct(Mailer $mailer, ?IRequest $request = null)
	{
		$this->mailer = $mailer;
		$this->request = $request;
	}

	public function send(Message $mail): void
	{
		$mail = clone $mail;

		if ($mail->getHeader('Message-ID') === null) {
			$mail->setHeader('Message-ID', $this->getRandomId());
		}

		$this->mailer->send($mail);
	}

	private function getRandomId(): string
	{
		$id = Random::generate();
		$host = $this->getHost();

		return "<$id@$host>";
	}

	private function getHost(): string
	{
		// phpcs:ignore SlevomatCodingStandard.ControlStructures.RequireTernaryOperator
		if ($this->request !== null && ($tmp = $this->request->getUrl()->getHost()) !== '') {
			$host = $tmp;
		} else {
			$host = $_SERVER['HTTP_HOST'] ?? php_uname('n');
		}

		$host = preg_replace('#[^\w.-]+#', '', $host);
		assert(is_string($host));

		return $host;
	}

}
