<?php

namespace Frappant\FrpFormAnswers\Command;

use Frappant\FrpFormAnswers\Domain\Model\FormEntryDemand;
use Frappant\FrpFormAnswers\Domain\Repository\FormEntryRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;

class MailAdminNotificationCommand extends Command
{
    public function __construct(
        private readonly FormEntryRepository $formEntryRepository,
        private readonly ViewFactoryInterface $viewFactory,
        private readonly MailerInterface $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Sends a notification mail with a list of not exportet form entries.')
            ->addArgument(
                'mailto',
                InputArgument::REQUIRED,
                'E-Mail Address the Mail should be sent to.'
            )
            ->addOption(
                'formname',
                '',
                InputOption::VALUE_OPTIONAL,
                'Name of the form to be checked.'
            )
            ->addOption(
                'title',
                '',
                InputOption::VALUE_OPTIONAL,
                'Subject Label.'
            );
    }

    /**
     * @param QueryResult<\Frappant\FrpFormAnswers\Domain\Model\FormEntry> $mails
     */
    public function generateMailBody(QueryResult $mails): string
    {
        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: ['EXT:frp_form_answers/Resources/Private/CommandTask/Templates'],
            partialRootPaths: ['EXT:frp_form_answers/Resources/Private/CommandTask/Partials'],
            layoutRootPaths: ['EXT:frp_form_answers/Resources/Private/CommandTask/Layouts'],
            format: 'html',
        );
        $view = $this->viewFactory->create($viewFactoryData);
        $view->assignMultiple(['mails' => $mails]);

        return $view->render('FormEntries/InMail');
    }

    /**
     * Email notification about sent forms.
     *
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $mailto = $input->getArgument('mailto');
        $formname = $input->getOption('formname');
        $title = $input->getOption('title');

        if (empty($mailto)) {
            throw new Exception('You need to provide at least one email address.', 2340297863);
        }

        $search = GeneralUtility::makeInstance(FormEntryDemand::class);
        $search->setAllPids(true);

        if ($formname) {
            $output->writeln('Searching for form ' . $formname);
            $search->setFormName($formname);
        } else {
            $output->writeln('Searching for no specific form');
        }

        $frommail = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'];
        if (!empty($frommail)) {
            $output->writeln('Default E-Mail Address: ' . $frommail);
            $from = $frommail;
        } else {
            throw new Exception("['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] is not set.", 5763504134);
        }
        $records = $this->formEntryRepository->findByDemand($search);

        if ($records->count()) {
            $output->writeln($records->count() . ' entries found');
            foreach ($records as $row) {
                $output->writeln('Sending entry with uid:' . $row->getUid());
                $row->setExported(true);
                try {
                    $this->formEntryRepository->update($row);
                } catch (IllegalObjectTypeException $e) {
                    $output->writeln($e->getMessage());
                    return Command::FAILURE;
                } catch (UnknownObjectException $e) {
                    $output->writeln($e->getMessage());
                    return Command::FAILURE;
                }
            }
            $body = $this->generateMailBody($records);
            $date = date('d/m/y');
            if (!empty($title)) {
                $subject = $title;
            } else {
                $subject = 'Scheduler mails update ' . $date;
            }
            $trim = GeneralUtility::trimExplode(',', $mailto, true);
            foreach ($trim as $singlemail) {
                $mail = new MailMessage();
                $mail
                    ->subject($subject)
                    ->from($from)
                    ->to($singlemail)
                    ->html($body);
                $this->mailer->send($mail);
            }
        } else {
            $output->writeln('Nothing to send.');
        }

        return Command::SUCCESS;
    }
}
