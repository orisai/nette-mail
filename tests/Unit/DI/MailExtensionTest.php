<?php declare(strict_types = 1);

namespace Tests\OriNette\Mail\Unit\DI;

use DOMDocument;
use DOMXPath;
use Nette\DI\Container;
use Nette\Mail\IMailer;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Utils\FileSystem;
use OriNette\DI\Boot\ManualConfigurator;
use OriNette\Mail\Command\MailerTestCommand;
use OriNette\Mail\Mailer\SetMessageIdMailer;
use OriNette\Mail\Mailer\ToArrayMailer;
use OriNette\Mail\Tracy\MailPanel;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Utils\Dependencies\DependenciesTester;
use PHPUnit\Framework\TestCase;
use Tracy\Bar;
use function dirname;
use function libxml_use_internal_errors;
use function mkdir;
use function sleep;
use function trim;
use const PHP_VERSION_ID;

final class MailExtensionTest extends TestCase
{

	private string $rootDir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->rootDir = dirname(__DIR__, 3);
		if (PHP_VERSION_ID < 8_01_00) {
			@mkdir("$this->rootDir/var/build");
		}
	}

	public function testWiring(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/MailExtension.minimal.neon');

		$container = $configurator->createContainer();

		$mailer = $container->getService('orisai.mail.mailer');
		self::assertInstanceOf(SetMessageIdMailer::class, $mailer);
		self::assertSame($mailer, $container->getByType(Mailer::class));
		self::assertSame($mailer, $container->getService('orisai.mail.setMessageIdMailer'));
		/** @phpstan-ignore-next-line */
		self::assertSame($mailer, $container->getByType(IMailer::class));

		$message = new Message();
		$toArrayMailer = $container->getByType(ToArrayMailer::class);
		self::assertCount(0, $toArrayMailer->getMessages());

		$mailer->send($message);
		self::assertCount(1, $toArrayMailer->getMessages());
	}

	public function testNoMailer(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/MailExtension.noMailer.neon');

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(
			<<<'MSG'
Context: Registering mailer service.
Problem: No mailer was registered via 'orisai.mail > mailers'.
Solution: Register at least one mailer.
MSG,
		);

		$configurator->createContainer();
	}

	public function testMessageId(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/MailExtension.messageId.neon');

		$container = $configurator->createContainer();

		$mailer = $container->getByType(IMailer::class);
		$message = new Message();
		$mailer->send($message);

		$toArrayMailer = $container->getByType(ToArrayMailer::class);
		$sentMessage = $toArrayMailer->getMessages()[0];
		$messageId = $sentMessage->getHeader('Message-ID');
		self::assertMatchesRegularExpression('#<(.+)@www.orisai.dev>#', $messageId);
	}

	public function testConsole(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/MailExtension.minimal.neon');

		$container = $configurator->createContainer();

		self::assertFalse($container->isCreated('orisai.mail.mailer'));

		$mailTestCommand = $container->getService('orisai.mail.command.mailTest');
		self::assertInstanceOf(MailerTestCommand::class, $mailTestCommand);

		self::assertTrue($container->isCreated('orisai.mail.mailer'));
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testNoConsole(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/MailExtension.minimal.neon');
		$configurator->addStaticParameters([
			'unique' => __FUNCTION__,
		]);

		DependenciesTester::addIgnoredPackages(['symfony/console']);
		$container = $configurator->createContainer();

		self::assertFalse($container->hasService('orisai.mail.command.mailTest'));
	}

	public function testDebugPanel(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/MailExtension.debugPanel.neon');

		$container = $configurator->createContainer();

		$mailPanel = $this->getMailPanel($container);
		$this->assertPanelRender($mailPanel, 0);

		$mailer = $container->getByType(IMailer::class);
		$mailer->send(new Message());
		$this->assertPanelRender($mailPanel, 1);
	}

	public function testDebugPanelPersistence(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/MailExtension.debugPanel.persistence.neon');

		$container = $configurator->createContainer(false);
		$mailDir = $container->getParameters()['mailDir'];
		// Delete files from previous run
		FileSystem::delete($mailDir);
		$container->initialize();

		$mailPanel = $this->getMailPanel($container);
		$this->assertPanelRender($mailPanel, 0);

		$mailer = $container->getByType(IMailer::class);
		$mailer->send(new Message());
		$mailer->send(new Message());
		$this->assertPanelRender($mailPanel, 2);

		// New container
		$container = $configurator->createContainer();

		$mailPanel = $this->getMailPanel($container);
		$this->assertPanelRender($mailPanel, 2);

		// Wait for previous mails to get old to trigger auto-cleanup
		sleep(2);
		// New container yet again
		$container = $configurator->createContainer();

		$mailer = $container->getByType(IMailer::class);
		$mailer->send(new Message());

		$mailPanel = $this->getMailPanel($container);
		$this->assertPanelRender($mailPanel, 1);
	}

	private function getMailPanel(Container $container): MailPanel
	{
		$bar = $container->getByType(Bar::class);
		$mailPanel = $bar->getPanel('orisai.mail');
		self::assertInstanceOf(MailPanel::class, $mailPanel);

		return $mailPanel;
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
