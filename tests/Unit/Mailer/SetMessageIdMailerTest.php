<?php declare(strict_types = 1);

namespace Tests\OriNette\Mail\Unit\Mailer;

use Nette\Http\Request;
use Nette\Http\RequestFactory;
use Nette\Http\UrlScript;
use Nette\Mail\Message;
use OriNette\Mail\Mailer\SetMessageIdMailer;
use OriNette\Mail\Mailer\ToArrayMailer;
use PHPUnit\Framework\TestCase;
use const PHP_SAPI;

final class SetMessageIdMailerTest extends TestCase
{

	public function testClone(): void
	{
		$inner = new ToArrayMailer();
		$mailer = new SetMessageIdMailer($inner);

		$message = new Message();
		$message->setHeader('Message-ID', 'test');

		$mailer->send($message);
		self::assertEquals($inner->getMessages()[0], $message);
		self::assertNotSame($inner->getMessages()[0], $message);
	}

	public function testSetMessageId(): void
	{
		$inner = new ToArrayMailer();
		$mailer = new SetMessageIdMailer($inner);

		$message = new Message();
		self::assertNull($message->getHeader('Message-ID'));

		$mailer->send($message);
		self::assertNull($message->getHeader('Message-ID'));

		$header = $inner->getMessages()[0]->getHeader('Message-ID');
		self::assertIsString($header);
		self::assertMatchesRegularExpression('#<(.+)@(.+)>#', $header);
	}

	public function testAcceptExistingMessageId(): void
	{
		$inner = new ToArrayMailer();
		$mailer = new SetMessageIdMailer($inner);

		$message = new Message();
		$message->setHeader('Message-ID', 'test');

		$mailer->send($message);
		self::assertSame('test', $message->getHeader('Message-ID'));
		self::assertSame('test', $inner->getMessages()[0]->getHeader('Message-ID'));
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testUrlHost(): void
	{
		$_SERVER['HTTP_HOST'] = 'www.server.com';

		$inner = new ToArrayMailer();
		$request = new Request(new UrlScript('https://www.url.com'));
		$mailer = new SetMessageIdMailer($inner, $request);

		$message = new Message();
		$mailer->send($message);

		$header = $inner->getMessages()[0]->getHeader('Message-ID');
		self::assertIsString($header);
		self::assertMatchesRegularExpression('#<(.+)@www.url.com>#', $header);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testEmptyUrlHostIsIgnored(): void
	{
		if (PHP_SAPI !== 'cli') {
			// We use RequestFactory to construct request with no url properly
			self::markTestSkipped('It is just easier to test it this in CLI.');
		}

		unset($_SERVER['HTTP_HOST']);

		$inner = new ToArrayMailer();
		// Happens in console when static url is not configured
		$request = (new RequestFactory())->fromGlobals();
		$mailer = new SetMessageIdMailer($inner, $request);

		$message = new Message();
		$mailer->send($message);

		$header = $inner->getMessages()[0]->getHeader('Message-ID');
		self::assertIsString($header);
		self::assertMatchesRegularExpression('#<(.+)@(.+)>#', $header);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testServerHost(): void
	{
		$_SERVER['HTTP_HOST'] = 'www.orisai.dev';

		$inner = new ToArrayMailer();
		$mailer = new SetMessageIdMailer($inner);

		$message = new Message();
		$mailer->send($message);

		$header = $inner->getMessages()[0]->getHeader('Message-ID');
		self::assertIsString($header);
		self::assertMatchesRegularExpression('#<(.+)@www.orisai.dev>#', $header);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPhpUnameHost(): void
	{
		unset($_SERVER['HTTP_HOST']);

		$inner = new ToArrayMailer();
		$mailer = new SetMessageIdMailer($inner);

		$message = new Message();
		$mailer->send($message);

		$header = $inner->getMessages()[0]->getHeader('Message-ID');
		self::assertIsString($header);
		self::assertMatchesRegularExpression('#<(.+)@(.+)>#', $header);
	}

}
