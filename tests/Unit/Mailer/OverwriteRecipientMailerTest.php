<?php declare(strict_types = 1);

namespace Tests\OriNette\Mail\Unit\Mailer;

use Nette\Mail\Message;
use OriNette\Mail\Mailer\OverwriteRecipientMailer;
use OriNette\Mail\Mailer\ToArrayMailer;
use PHPUnit\Framework\TestCase;
use function array_pop;

final class OverwriteRecipientMailerTest extends TestCase
{

	public function testMinimal(): void
	{
		$toArrayMailer = new ToArrayMailer();
		$mailer = new OverwriteRecipientMailer(
			$toArrayMailer,
			[
				'dobbythesockmaster@elfmail.com' => 'Dobby, the free elf',
			],
		);

		$mail = new Message();
		$mail->setFrom('hermione-granger@hogwarts.co.uk', 'Hermione Granger');
		$mail->addTo('harry-potter@hogwarts.co.uk', 'Harry Potter');

		$mailer->send($mail);

		$mails = $toArrayMailer->getMessages();
		$sentMail = array_pop($mails);
		self::assertNotNull($sentMail);

		self::assertSame(['hermione-granger@hogwarts.co.uk' => 'Hermione Granger'], $mail->getFrom());
		self::assertSame($mail->getFrom(), $sentMail->getFrom());

		self::assertSame(
			[
				'harry-potter@hogwarts.co.uk' => 'Harry Potter',
			],
			$mail->getHeader('To'),
		);
		self::assertSame(
			[
				'dobbythesockmaster@elfmail.com' => 'Dobby, the free elf',
			],
			$sentMail->getHeader('To'),
		);
	}

	public function testChange(): void
	{
		$toArrayMailer = new ToArrayMailer();
		$mailer = new OverwriteRecipientMailer(
			$toArrayMailer,
			[
				'MasterMaceWindu@VaapadTemple.org' => 'Mace Windu',
				'JediKnightAhsoka@FulcrumOrder.net' => null,
			],
			[
				'ObiWanKenobi@TheForceIsWithMe.com' => 'Obi-Wan Kenobi',
				'QuiGonJinn@LivingForceAcademy.org' => null,
			],
			[
				'MasterYoda@WiseJediCouncil.org' => 'Yoda',
				'PadawanEzra@LothalAcademy.net' => null,
			],
		);

		$mail = new Message();
		$mail->addTo('CloneCaptainRex@501stCommand.net', 'Captain Rex');
		$mail->addTo('EchoElite@DominoSquadron.org', 'Echo');
		$mail->addCc('FivesFury@ARCtroopers.net', 'Fives');
		$mail->addCc('HeavyHavoc@HeavyWeaponsCorps.org', 'Heavy');
		$mail->addBcc('TechWhiz@CloneEngineers.com', 'Tech');
		$mail->addBcc('JesseJuggernaut@327thBattalion.org', 'Jesse');

		$mailer->send($mail);

		$mails = $toArrayMailer->getMessages();
		$sentMail = array_pop($mails);
		self::assertNotNull($sentMail);

		self::assertSame(
			[
				'MasterMaceWindu@VaapadTemple.org' => 'Mace Windu',
				'JediKnightAhsoka@FulcrumOrder.net' => null,
			],
			$sentMail->getHeader('To'),
		);
		self::assertSame(
			[
				'ObiWanKenobi@TheForceIsWithMe.com' => 'Obi-Wan Kenobi',
				'QuiGonJinn@LivingForceAcademy.org' => null,
			],
			$sentMail->getHeader('Cc'),
		);
		self::assertSame(
			[
				'MasterYoda@WiseJediCouncil.org' => 'Yoda',
				'PadawanEzra@LothalAcademy.net' => null,
			],
			$sentMail->getHeader('Bcc'),
		);
	}

	public function testSingleTo(): void
	{
		$toArrayMailer = new ToArrayMailer();
		$mailer = new OverwriteRecipientMailer(
			$toArrayMailer,
			'FrodoBaggins@TheShire.te',
		);

		$mail = new Message();
		$mailer->send($mail);

		$mails = $toArrayMailer->getMessages();
		$sentMail = array_pop($mails);
		self::assertNotNull($sentMail);

		self::assertSame(
			[
				'FrodoBaggins@TheShire.te' => null,
			],
			$sentMail->getHeader('To'),
		);
		self::assertNull($sentMail->getHeader('Cc'));
		self::assertNull($sentMail->getHeader('Bcc'));
	}

	public function testOriginalsBackup(): void
	{
		$toArrayMailer = new ToArrayMailer();
		$mailer = new OverwriteRecipientMailer(
			$toArrayMailer,
			[],
		);

		$mail = new Message();
		$mail->addTo('blossom@power.puff');
		$mail->addTo('clover@totally.spies', 'Clover');
		$mail->addCc('bubbles@power.puff');
		$mail->addCc('sam@totally.spies', 'Sam');
		$mail->addBcc('buttercup@power.puff');
		$mail->addBcc('alex@totally.spies', 'Alex');

		self::assertIsArray($mail->getHeader('To'));
		self::assertIsArray($mail->getHeader('Cc'));
		self::assertIsArray($mail->getHeader('Bcc'));

		$mailer->send($mail);

		$mails = $toArrayMailer->getMessages();
		$sentMail = array_pop($mails);
		self::assertNotNull($sentMail);

		self::assertNull($sentMail->getHeader('To'));
		self::assertNull($sentMail->getHeader('Cc'));
		self::assertNull($sentMail->getHeader('Bcc'));

		self::assertSame(
			[
				'blossom@power.puff' => null,
				'clover@totally.spies' => 'Clover',
			],
			$sentMail->getHeader('X-Original-To'),
		);
		self::assertSame(
			[
				'bubbles@power.puff' => null,
				'sam@totally.spies' => 'Sam',
			],
			$sentMail->getHeader('X-Original-Cc'),
		);
		self::assertSame(
			[
				'buttercup@power.puff' => null,
				'alex@totally.spies' => 'Alex',
			],
			$sentMail->getHeader('X-Original-Bcc'),
		);
	}

}
