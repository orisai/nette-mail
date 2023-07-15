<?php declare(strict_types = 1);

namespace Tests\OriNette\Mail\Unit\Tracy;

use DOMDocument;
use DOMXPath;
use Generator;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Mail\Message;
use OriNette\Http\Tester\TestResponse;
use OriNette\Mail\Mailer\TracyPanelMailer;
use OriNette\Mail\Tracy\MailPanel;
use OriNette\Mail\Tracy\MailPanelRequestTermination;
use Orisai\VFS\VFS;
use PHPUnit\Framework\TestCase;
use Tracy\Debugger;
use Tracy\Helpers;
use function array_key_first;
use function file_put_contents;
use function libxml_use_internal_errors;
use function trim;

final class MailPanelTest extends TestCase
{

	/**
	 * @dataProvider providePath
	 */
	public function testRender(?string $path): void
	{
		$mailer = new TracyPanelMailer($path);
		$panel = new MailPanel($mailer, null, null, $path);

		$this->assertPanelRender($panel, 0);

		$message = new Message();
		$message->setHtmlBody('<h1>Hello world!</h1>');
		$message->addAttachment('attachment.txt', 'content');
		$mailer->send($message);

		$this->assertPanelRender($panel, 1);
	}

	public function providePath(): Generator
	{
		yield [null];
		yield [VFS::register() . '://path'];
	}

	public function testPersistentRenderDifferences(): void
	{
		$path = VFS::register() . '://path';
		$message = (new Message())->build();

		$persistentMailer = new TracyPanelMailer($path);
		$persistentMailer->send($message);
		$persistentPanel = new MailPanel($persistentMailer);

		$arrayMailer = new TracyPanelMailer();
		$arrayMailer->send($message);
		$arrayPanel = new MailPanel($arrayMailer);

		self::assertSame($arrayPanel->getTab(), $persistentPanel->getTab());
		self::assertNotSame($arrayPanel->getPanel(), $persistentPanel->getPanel());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testUnsupportedHandle(): void
	{
		Debugger::$productionMode = Debugger::Development;

		$path = VFS::register() . '://path';
		$mailer = new TracyPanelMailer($path);
		$mailer->send(new Message());

		$url = (new UrlScript('https://orisai.dev/foo'))
			->withQuery([
				'do' => 'unsupported',
				'orisai-action' => 'detail',
				'orisai-id' => 'non-existent',
			]);
		$request = new Request($url);
		$response = new TestResponse();

		$output = Helpers::capture(static function () use ($mailer, $request, $response, $path): void {
			new MailPanel($mailer, $request, $response, $path);
		});
		self::assertEquals($response, new TestResponse());
		self::assertEmpty($output);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDetail(): void
	{
		Debugger::$productionMode = Debugger::Development;

		$path = VFS::register() . '://path';
		$mailer = new TracyPanelMailer($path);
		$mailer->send(new Message());

		$url = (new UrlScript('https://orisai.dev/foo'))
			->withQuery([
				'do' => 'orisai-mail-panel',
				'orisai-action' => 'detail',
				'orisai-id' => array_key_first($mailer->getMessages()),
			]);
		$request = new Request($url);
		$response = new TestResponse();

		$output = Helpers::capture(static function () use ($mailer, $request, $response, $path): void {
			try {
				new MailPanel($mailer, $request, $response, $path);
			} catch (MailPanelRequestTermination $exception) {
				// Noop
			}

			self::assertTrue(isset($exception));
		});
		self::assertSame('Content-Type: text/html', $response->getHeader('Content-Type'));
		self::assertNotEmpty($output);
	}

	/**
	 * @runInSeparateProcess
	 * @dataProvider provideNonExistentMessage
	 */
	public function testNonExistentMessage(string $action): void
	{
		Debugger::$productionMode = Debugger::Development;

		$path = VFS::register() . '://path';
		$mailer = new TracyPanelMailer($path);

		$url = (new UrlScript('https://orisai.dev/foo'))
			->withQuery([
				'do' => 'orisai-mail-panel',
				'orisai-action' => $action,
				'orisai-id' => 'non-existent',
				'orisai-attachment-id' => '0',
			]);
		$request = new Request($url);
		$response = new TestResponse();

		$output = Helpers::capture(static function () use ($mailer, $request, $response, $path): void {
			try {
				new MailPanel($mailer, $request, $response, $path);
			} catch (MailPanelRequestTermination $exception) {
				// Noop
			}

			self::assertTrue(isset($exception));
		});
		self::assertSame(302, $response->getCode());
		self::assertSame('Location: https://orisai.dev/foo', $response->getHeader('Location'));
		self::assertEmpty($output);
	}

	public function provideNonExistentMessage(): Generator
	{
		yield ['detail'];
		yield ['source'];
		yield ['attachment'];
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSource(): void
	{
		Debugger::$productionMode = Debugger::Development;

		$path = VFS::register() . '://path';
		$mailer = new TracyPanelMailer($path);
		$mailer->send(new Message());

		$url = (new UrlScript('https://orisai.dev/foo'))
			->withQuery([
				'do' => 'orisai-mail-panel',
				'orisai-action' => 'source',
				'orisai-id' => array_key_first($mailer->getMessages()),
			]);
		$request = new Request($url);
		$response = new TestResponse();

		$output = Helpers::capture(static function () use ($mailer, $request, $response, $path): void {
			try {
				new MailPanel($mailer, $request, $response, $path);
			} catch (MailPanelRequestTermination $exception) {
				// Noop
			}

			self::assertTrue(isset($exception));
		});
		self::assertSame('Content-Type: text/plain', $response->getHeader('Content-Type'));
		self::assertNotEmpty($output);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testAttachment(): void
	{
		Debugger::$productionMode = Debugger::Development;

		$path = VFS::register() . '://path';
		$mailer = new TracyPanelMailer($path);

		$message = new Message();
		$attachment = "$path/attachment.txt";
		file_put_contents($attachment, 'content');
		$message->addAttachment($attachment);
		$mailer->send($message);

		$url = (new UrlScript('https://orisai.dev/foo'))
			->withQuery([
				'do' => 'orisai-mail-panel',
				'orisai-action' => 'attachment',
				'orisai-id' => array_key_first($mailer->getMessages()),
				'orisai-attachment-id' => '0',
			]);
		$request = new Request($url);
		$response = new TestResponse();

		$output = Helpers::capture(static function () use ($mailer, $request, $response, $path): void {
			try {
				new MailPanel($mailer, $request, $response, $path);
			} catch (MailPanelRequestTermination $exception) {
				// Noop
			}

			self::assertTrue(isset($exception));
		});
		self::assertSame('Content-Type: text/plain', $response->getHeader('Content-Type'));
		self::assertSame('content', $output);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testNonExistentAttachment(): void
	{
		Debugger::$productionMode = Debugger::Development;

		$path = VFS::register() . '://path';
		$mailer = new TracyPanelMailer($path);

		$message = new Message();
		$mailer->send($message);

		$url = (new UrlScript('https://orisai.dev/foo'))
			->withQuery([
				'do' => 'orisai-mail-panel',
				'orisai-action' => 'attachment',
				'orisai-id' => array_key_first($mailer->getMessages()),
				'orisai-attachment-id' => '0',
			]);
		$request = new Request($url);
		$response = new TestResponse();

		$output = Helpers::capture(static function () use ($mailer, $request, $response, $path): void {
			try {
				new MailPanel($mailer, $request, $response, $path);
			} catch (MailPanelRequestTermination $exception) {
				// Noop
			}

			self::assertTrue(isset($exception));
		});
		self::assertSame(302, $response->getCode());
		self::assertSame('Location: https://orisai.dev/foo', $response->getHeader('Location'));
		self::assertEmpty($output);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteById(): void
	{
		Debugger::$productionMode = Debugger::Development;

		$path = VFS::register() . '://path';
		$mailer = new TracyPanelMailer($path);
		$mailer->send(new Message());

		$url = (new UrlScript('https://orisai.dev/foo'))
			->withQuery([
				'do' => 'orisai-mail-panel',
				'orisai-action' => 'delete',
				'orisai-id' => array_key_first($mailer->getMessages()),
			]);
		$request = new Request($url);
		$response = new TestResponse();

		$output = Helpers::capture(static function () use ($mailer, $request, $response, $path): void {
			try {
				new MailPanel($mailer, $request, $response, $path);
			} catch (MailPanelRequestTermination $exception) {
				// Noop
			}

			self::assertTrue(isset($exception));
		});
		self::assertSame(302, $response->getCode());
		self::assertSame('Location: https://orisai.dev/foo', $response->getHeader('Location'));
		self::assertEmpty($output);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteAll(): void
	{
		Debugger::$productionMode = Debugger::Development;

		$path = VFS::register() . '://path';
		$mailer = new TracyPanelMailer($path);
		$mailer->send(new Message());

		$url = (new UrlScript('https://orisai.dev/foo'))
			->withQuery([
				'do' => 'orisai-mail-panel',
				'orisai-action' => 'delete',
			]);
		$request = new Request($url);
		$response = new TestResponse();

		$output = Helpers::capture(static function () use ($mailer, $request, $response, $path): void {
			try {
				new MailPanel($mailer, $request, $response, $path);
			} catch (MailPanelRequestTermination $exception) {
				// Noop
			}

			self::assertTrue(isset($exception));
		});
		self::assertSame(302, $response->getCode());
		self::assertSame('Location: https://orisai.dev/foo', $response->getHeader('Location'));
		self::assertEmpty($output);
	}

	private function assertPanelRender(MailPanel $mailPanel, int $count): void
	{
		$tab = $mailPanel->getTab();
		self::assertNotSame('', $tab);
		self::assertNotSame('', $mailPanel->getPanel());

		libxml_use_internal_errors(true);
		$dom = new DOMDocument();
		$dom->loadHTML($tab);
		$xpath = new DOMXPath($dom);

		$nodes = $xpath->query('//span[@class="tracy-label"]');
		self::assertNotFalse($nodes);
		$node = $nodes->item(0);
		self::assertNotNull($node);
		$content = trim($node->nodeValue ?? '');

		self::assertSame((string) $count, $content);
	}

}
