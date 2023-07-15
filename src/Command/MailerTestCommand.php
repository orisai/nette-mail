<?php declare(strict_types = 1);

namespace OriNette\Mail\Command;

use Nette\Http\IRequest;
use Nette\Http\RequestFactory;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MailerTestCommand extends Command
{

	private Mailer $mailer;

	private IRequest $request;

	public function __construct(Mailer $mailer, ?IRequest $request = null)
	{
		$this->request = $request ?? (new RequestFactory())->fromGlobals();
		parent::__construct();
		$this->mailer = $mailer;
	}

	public static function getDefaultName(): string
	{
		return 'mailer:test';
	}

	public static function getDefaultDescription(): string
	{
		return 'Test mailer by sending an email';
	}

	protected function configure(): void
	{
		$this->addArgument('to', InputArgument::REQUIRED, 'Recipient of the message');

		$host = $this->request->getUrl()->getHost();
		$from = $host !== '' ? "from@$host" : 'from@example.org';
		$this->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Sender of the message', $from);
		$this->addOption('subject', null, InputOption::VALUE_OPTIONAL, 'Subject of the message', 'Mailer test');
		$this->addOption('body', null, InputOption::VALUE_OPTIONAL, 'Body of the message', 'Example message');

		$this->setHelp(
			<<<'EOF'
Test mailer by sending an email:
<info>php %command.full_name% to@example.com</info>
EOF,
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$message = new Message();
		$message->addTo($input->getArgument('to'));
		$message->setFrom($input->getOption('from'));
		$message->setSubject($input->getOption('subject'));
		$message->setHtmlBody($input->getOption('body'));

		$this->mailer->send($message);

		$output->writeln('<info>Mail sent.</info>');

		return self::SUCCESS;
	}

}
