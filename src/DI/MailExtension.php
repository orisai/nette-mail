<?php declare(strict_types = 1);

namespace OriNette\Mail\DI;

use DateTimeImmutable;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\Literal;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use OriNette\DI\Definitions\DefinitionsLoader;
use OriNette\Mail\Command\MailerTestCommand;
use OriNette\Mail\Mailer\MultiMailer;
use OriNette\Mail\Mailer\SetMessageIdMailer;
use OriNette\Mail\Mailer\TracyPanelMailer;
use OriNette\Mail\Tracy\MailPanel;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Exceptions\Message;
use Orisai\Utils\Dependencies\Dependencies;
use stdClass;
use Tracy\Bar;
use function assert;

/**
 * @property-read stdClass $config
 */
final class MailExtension extends CompilerExtension
{

	/** @var list<Definition|Reference> */
	private array $innerMailerDefinitions = [];

	private ServiceDefinition $panelDefinition;

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'debug' => Expect::structure([
				'panel' => Expect::bool(false),
				'tempDir' => Expect::anyOf(Expect::string(), Expect::null())->default(null),
				'cleanup' => Expect::anyOf(Expect::string(), Expect::null())->default(null),
			]),
			'mailers' => Expect::arrayOf(
				DefinitionsLoader::schema(),
			),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->config;
		$loader = new DefinitionsLoader($this->compiler);

		$this->registerTracyMailer($config, $builder);
		$this->registerInnerMailers($config, $loader);

		$mainMailer = $this->registerMultiMailer($builder);
		$mainMailer = $this->registerMessageIdMailer($builder, $mainMailer);
		$this->autowireMainMailer($builder, $mainMailer);

		$this->registerConsoleIntegration($builder);
	}

	private function registerTracyMailer(stdClass $config, ContainerBuilder $builder): void
	{
		if (!$config->debug->panel) {
			return;
		}

		$mailerDefinition = $builder->addDefinition($this->prefix('tracy.mailer'))
			->setFactory(TracyPanelMailer::class, [
				'tempDir' => $config->debug->tempDir,
			])
			->setAutowired(false);
		$this->innerMailerDefinitions[] = $mailerDefinition;

		if ($config->debug->cleanup !== null) {
			$mailerDefinition->addSetup('setAutoCleanup', [
				new Literal('new ' . DateTimeImmutable::class . '(\'' . $config->debug->cleanup . '\')'),
			]);
		}

		$this->panelDefinition = $builder->addDefinition($this->prefix('tracy.panel'))
			->setFactory(MailPanel::class, [
				'mailer' => $mailerDefinition,
				'tempDir' => $config->debug->tempDir,
			])
			->setAutowired(false);
	}

	public static function setupTracyMailPanel(string $name, Bar $bar, MailPanel $panel): void
	{
		$bar->addPanel($panel, $name);
	}

	private function registerInnerMailers(stdClass $config, DefinitionsLoader $loader): void
	{
		foreach ($config->mailers as $mailerId => $mailerConfig) {
			$this->innerMailerDefinitions[] = $loader->loadDefinitionFromConfig(
				$mailerConfig,
				$this->prefix("mailer.$mailerId"),
			);
		}
	}

	private function registerMultiMailer(ContainerBuilder $builder): ServiceDefinition
	{
		$this->checkAnyMailerIsAdded();

		return $builder->addDefinition($this->prefix('multiMailer'))
			->setFactory(MultiMailer::class, [
				'mailers' => $this->innerMailerDefinitions,
			])
			->setAutowired(false);
	}

	private function checkAnyMailerIsAdded(): void
	{
		if ($this->innerMailerDefinitions !== []) {
			return;
		}

		$message = Message::create()
			->withContext('Registering mailer service.')
			->withProblem("No mailer was registered via '$this->name > mailers'.")
			->withSolution('Register at least one mailer.');

		throw InvalidState::create()
			->withMessage($message);
	}

	private function registerMessageIdMailer(
		ContainerBuilder $builder,
		ServiceDefinition $wrappedMailer
	): ServiceDefinition
	{
		return $builder->addDefinition($this->prefix('setMessageIdMailer'))
			->setFactory(SetMessageIdMailer::class, [
				'mailer' => $wrappedMailer,
			])
			->setAutowired(false);
	}

	private function autowireMainMailer(ContainerBuilder $builder, ServiceDefinition $mainMailer): void
	{
		$mainMailer->setAutowired();

		$name = $mainMailer->getName();
		assert($name !== null);

		$builder->addAlias($this->prefix('mailer'), $name);
	}

	private function registerConsoleIntegration(ContainerBuilder $builder): void
	{
		if (!Dependencies::isPackageLoaded('symfony/console')) {
			return;
		}

		$builder->addDefinition($this->prefix('command.mailTest'))
			->setFactory(MailerTestCommand::class);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->config;

		$this->addPanelToTracyBar($builder, $config);
	}

	private function addPanelToTracyBar(ContainerBuilder $builder, stdClass $config): void
	{
		if (!$config->debug->panel) {
			return;
		}

		// Early initialization ensures panel action is handled in time
		$init = $this->getInitialization();
		$init->addBody(
			self::class . '::setupTracyMailPanel(?, $this->getService(?), $this->getService(?));',
			[
				$this->name,
				$builder->getDefinitionByType(Bar::class)->getName(),
				$this->panelDefinition->getName(),
			],
		);
	}

}
