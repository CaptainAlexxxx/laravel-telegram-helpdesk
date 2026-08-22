<?php

namespace App\Telegram;

use App\Telegram\Commands\CloseTicketCommand;
use App\Telegram\Commands\CreateBroadcastCommand;
use App\Telegram\Commands\FaqCommand;
use App\Telegram\Commands\ListBroadcastsCommand;
use App\Telegram\Commands\MyTicketsCommand;
use App\Telegram\Commands\NewTicketCommand;
use App\Telegram\Commands\ScheduleCommand;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Handlers\AgentMediaGroupHandler;
use App\Telegram\Handlers\AgentMessageHandler;
use App\Telegram\Handlers\CallbackQueryHandler;
use App\Telegram\Handlers\ClientMediaGroupHandler;
use App\Telegram\Handlers\MessageHandler;
use SergiX44\Nutgram\Nutgram;

class RegisterHandlers
{
    public static function register(Nutgram $bot): void
    {
        $bot->onCommand('start', StartCommand::class);
        $bot->onCommand('new_ticket', NewTicketCommand::class);
        $bot->onCommand('close_ticket', CloseTicketCommand::class);
        $bot->onCommand('faq', FaqCommand::class);
        $bot->onCommand('my_tickets', MyTicketsCommand::class);
        $bot->onCommand('mmsg', CreateBroadcastCommand::class);
        $bot->onCommand('listmsg', ListBroadcastsCommand::class);
        $bot->onCommand('schedule', ScheduleCommand::class);

        $bot->onCallbackQuery(CallbackQueryHandler::class);

        // Album handlers must come first: they swallow media_group_id messages
        // that the single-message handlers below would otherwise duplicate.
        $bot->onMessage(ClientMediaGroupHandler::class);
        $bot->onMessage(AgentMediaGroupHandler::class);

        $bot->onMessage(AgentMessageHandler::class);
        $bot->onMessage(MessageHandler::class);
    }
}
