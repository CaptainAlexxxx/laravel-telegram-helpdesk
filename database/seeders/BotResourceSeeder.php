<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BotResourceSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure locales exist before seeding resources
        $this->call(BotLocaleSeeder::class);

        $ukLocaleId = DB::table('bot_locales')->where('code', 'uk')->value('id');
        $enLocaleId = DB::table('bot_locales')->where('code', 'en')->value('id');
        $ruLocaleId = DB::table('bot_locales')->where('code', 'ru')->value('id');

        if (! $ukLocaleId || ! $enLocaleId || ! $ruLocaleId) {
            $this->command?->error('Missing locales in bot_locales table. Cannot seed resources.');

            return;
        }

        $resources = [
            // Welcome messages
            [
                'type' => 'message',
                'resource_key' => 'messages.welcome',
                'description' => 'Welcome message on /start',
                'values' => [
                    $ukLocaleId => 'Вітаємо! Я бот технічної підтримки. Опишіть вашу проблему, і ми допоможемо.',
                    $enLocaleId => 'Welcome! I am a technical support bot. Describe your issue and we will help.',
                    $ruLocaleId => 'Добро пожаловать! Я бот технической поддержки. Опишите вашу проблему, и мы поможем.',
                ],
            ],

            // Buttons
            [
                'type' => 'button',
                'resource_key' => 'button.problem_solved',
                'description' => 'Problem solved button',
                'values' => [
                    $ukLocaleId => '✅ Проблему вирішено',
                    $enLocaleId => '✅ Problem solved',
                    $ruLocaleId => '✅ Проблема решена',
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.close_ticket',
                'description' => 'Close ticket button',
                'values' => [
                    $ukLocaleId => '🔒 Закрити звернення',
                    $enLocaleId => '🔒 Close ticket',
                    $ruLocaleId => '🔒 Закрыть обращение',
                ],
            ],

            // Ticket messages
            [
                'type' => 'message',
                'resource_key' => 'messages.ticket_created',
                'description' => 'Ticket created confirmation',
                'values' => [
                    $ukLocaleId => '🎫 Ваше звернення передано команді підтримки. Очікуйте відповіді.',
                    $enLocaleId => '🎫 Your ticket has been sent to the support team. Please wait for a response.',
                    $ruLocaleId => '🎫 Ваше обращение передано команде поддержки. Ожидайте ответа.',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.ticket_closed',
                'description' => 'Ticket closed message',
                'values' => [
                    $ukLocaleId => '✅ Ваше звернення закрито. Дякуємо, що звернулися до нас!',
                    $enLocaleId => '✅ Your ticket has been closed. Thank you for contacting us!',
                    $ruLocaleId => '✅ Ваше обращение закрыто. Спасибо, что обратились к нам!',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.rate_request',
                'description' => 'Request for rating',
                'values' => [
                    $ukLocaleId => '⭐ Будь ласка, оцініть якість підтримки від 1 до 5:',
                    $enLocaleId => '⭐ Please rate the quality of support from 1 to 5:',
                    $ruLocaleId => '⭐ Пожалуйста, оцените качество поддержки от 1 до 5:',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.describe_issue',
                'description' => 'Prompt to describe issue for new ticket',
                'values' => [
                    $ukLocaleId => '📝 Будь ласка, опишіть вашу проблему в наступному повідомленні:',
                    $enLocaleId => '📝 Please describe your issue in the next message:',
                    $ruLocaleId => '📝 Пожалуйста, опишите вашу проблему в следующем сообщении:',
                ],
            ],
            // FAQ Header
            [
                'type' => 'message',
                'resource_key' => 'messages.faq_header',
                'description' => 'FAQ header message',
                'values' => [
                    $ukLocaleId => 'Часті питання:',
                    $enLocaleId => 'Frequently Asked Questions:',
                    $ruLocaleId => 'Часто задаваемые вопросы:',
                ],
            ],

            // FAQ Questions and Answers
            [
                'type' => 'message',
                'resource_key' => 'faq.question_1',
                'description' => 'FAQ Question 1',
                'values' => [
                    $ukLocaleId => 'Як створити нове звернення?',
                    $enLocaleId => 'How to create a new ticket?',
                    $ruLocaleId => 'Как создать новое обращение?',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'faq.answer_1',
                'description' => 'FAQ Answer 1',
                'values' => [
                    $ukLocaleId => 'Просто надішліть нам повідомлення з описом вашої проблеми. Бот автоматично створить звернення та передасть його команді підтримки.',
                    $enLocaleId => 'Simply send us a message describing your issue. The bot will automatically create a ticket and forward it to the support team.',
                    $ruLocaleId => 'Просто отправьте нам сообщение с описанием вашей проблемы. Бот автоматически создаст обращение и передаст его команде поддержки.',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'faq.question_2',
                'description' => 'FAQ Question 2',
                'values' => [
                    $ukLocaleId => 'Як закрити звернення?',
                    $enLocaleId => 'How to close a ticket?',
                    $ruLocaleId => 'Как закрыть обращение?',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'faq.answer_2',
                'description' => 'FAQ Answer 2',
                'values' => [
                    $ukLocaleId => 'Ви можете закрити звернення натиснувши кнопку "Проблему вирішено" або використавши команду /close_ticket.',
                    $enLocaleId => 'You can close a ticket by clicking the "Problem solved" button or using the /close_ticket command.',
                    $ruLocaleId => 'Вы можете закрыть обращение, нажав кнопку "Проблема решена" или используя команду /close_ticket.',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'faq.question_3',
                'description' => 'FAQ Question 3',
                'values' => [
                    $ukLocaleId => 'Як швидко відповідає підтримка?',
                    $enLocaleId => 'How fast does support respond?',
                    $ruLocaleId => 'Как быстро отвечает поддержка?',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'faq.answer_3',
                'description' => 'FAQ Answer 3',
                'values' => [
                    $ukLocaleId => 'Ми намагаємося відповідати на всі звернення протягом години. У складних випадках час відповіді може бути більшим.',
                    $enLocaleId => 'We try to respond to all tickets within an hour. In complex cases, response time may be longer.',
                    $ruLocaleId => 'Мы стараемся отвечать на все обращения в течение часа. В сложных случаях время ответа может быть больше.',
                ],
            ],

            // Buttons
            [
                'type' => 'button',
                'resource_key' => 'button.back_to_faq',
                'description' => 'Back to FAQ button',
                'values' => [
                    $ukLocaleId => 'Назад до FAQ',
                    $enLocaleId => 'Back to FAQ',
                    $ruLocaleId => 'Назад к FAQ',
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.back_to_menu',
                'description' => 'Back to menu button',
                'values' => [
                    $ukLocaleId => 'Назад до меню',
                    $enLocaleId => 'Back to menu',
                    $ruLocaleId => 'Назад в меню',
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.leave_comment',
                'description' => 'Leave comment button after rating',
                'values' => [
                    $ukLocaleId => 'Залишити коментар',
                    $enLocaleId => 'Leave a comment',
                    $ruLocaleId => 'Оставить комментарий',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.rating_thanks',
                'description' => 'Thank you message after rating',
                'values' => [
                    $ukLocaleId => 'Дякуємо за вашу оцінку!',
                    $enLocaleId => 'Thank you for your rating!',
                    $ruLocaleId => 'Спасибо за вашу оценку!',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.enter_review_comment',
                'description' => 'Prompt to enter review comment',
                'values' => [
                    $ukLocaleId => 'Будь ласка, введіть ваш коментар про якість підтримки:',
                    $enLocaleId => 'Please enter your comment about the support quality:',
                    $ruLocaleId => 'Пожалуйста, введите ваш комментарий о качестве поддержки:',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.review_comment_saved',
                'description' => 'Confirmation message after saving comment',
                'values' => [
                    $ukLocaleId => 'Дякуємо! Ваш коментар збережено.',
                    $enLocaleId => 'Thank you! Your comment has been saved.',
                    $ruLocaleId => 'Спасибо! Ваш комментарий сохранен.',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.ticket_auto_closed',
                'description' => 'Message when ticket is automatically closed',
                'values' => [
                    $ukLocaleId => 'Ваше звернення автоматично закрито через відсутність активності. Якщо вам все ще потрібна допомога, надішліть нове повідомлення.',
                    $enLocaleId => 'Your ticket has been automatically closed due to inactivity. If you still need help, please send a new message.',
                    $ruLocaleId => 'Ваше обращение автоматически закрыто из-за отсутствия активности. Если вам все еще нужна помощь, отправьте новое сообщение.',
                ],
            ],
            // My Tickets
            [
                'type' => 'message',
                'resource_key' => 'messages.no_tickets',
                'description' => 'No tickets message',
                'values' => [
                    $ukLocaleId => 'У вас ще немає звернень. Надішліть повідомлення, щоб створити нове.',
                    $enLocaleId => 'You have no tickets yet. Send a message to create one.',
                    $ruLocaleId => 'У вас еще нет обращений. Отправьте сообщение, чтобы создать новое.',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.my_tickets_header',
                'description' => 'My tickets header',
                'values' => [
                    $ukLocaleId => '<b>Ваші звернення:</b>',
                    $enLocaleId => '<b>Your tickets:</b>',
                    $ruLocaleId => '<b>Ваши обращения:</b>',
                ],
            ],

            // Status labels
            [
                'type' => 'message',
                'resource_key' => 'status.open',
                'description' => 'Open status',
                'values' => [
                    $ukLocaleId => 'Відкрито',
                    $enLocaleId => 'Open',
                    $ruLocaleId => 'Открыто',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'status.pending',
                'description' => 'Pending status',
                'values' => [
                    $ukLocaleId => 'В обробці',
                    $enLocaleId => 'Pending',
                    $ruLocaleId => 'В обработке',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'status.closed',
                'description' => 'Closed status',
                'values' => [
                    $ukLocaleId => 'Закрито',
                    $enLocaleId => 'Closed',
                    $ruLocaleId => 'Закрыто',
                ],
            ],

            // Operator call message
            [
                'type' => 'message',
                'resource_key' => 'messages.operator_called',
                'description' => 'Operator called message',
                'values' => [
                    $ukLocaleId => 'Оператора викликано!',
                    $enLocaleId => 'An operator has been requested!',
                    $ruLocaleId => 'Оператор вызван!',
                ],
            ],
            // Broadcast / Mass messaging
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_select_role',
                'description' => 'Broadcast: step 1 - select recipients',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x93\xA2 <b>Нова розсилка</b>\n\nКрок 1/3. Оберіть отримувачів:",
                    $enLocaleId => "\xF0\x9F\x93\xA2 <b>New broadcast</b>\n\nStep 1/3. Choose recipients:",
                    $ruLocaleId => "\xF0\x9F\x93\xA2 <b>Новая рассылка</b>\n\nШаг 1/3. Выберите получателей:",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_role_selected',
                'description' => 'Broadcast: step 2 - send message prompt',
                'values' => [
                    $ukLocaleId => "\xE2\x9C\x85 Отримувачі: <b>{role}</b>\n\n\xE2\x9C\x89\xEF\xB8\x8F <b>Крок 2/3.</b> Надішліть повідомлення для розсилки.\nМожна надіслати текст або фото (з підписом або без).\n\nДля скасування: /mmsg",
                    $enLocaleId => "\xE2\x9C\x85 Recipients: <b>{role}</b>\n\n\xE2\x9C\x89\xEF\xB8\x8F <b>Step 2/3.</b> Send a message for the broadcast.\nYou can send text or a photo (with or without caption).\n\nTo cancel: /mmsg",
                    $ruLocaleId => "\xE2\x9C\x85 Получатели: <b>{role}</b>\n\n\xE2\x9C\x89\xEF\xB8\x8F <b>Шаг 2/3.</b> Отправьте сообщение для рассылки.\nМожно отправить текст или фото (с подписью или без).\n\nДля отмены: /mmsg",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_awaiting_date',
                'description' => 'Broadcast: step 3 - enter date prompt',
                'values' => [
                    $ukLocaleId => "\xE2\x9C\x85 Повідомлення отримано.\n\n\xF0\x9F\x93\x85 <b>Крок 3/3.</b> Введіть дату розсилки:\n<code>дд-мм-рррр</code> або <code>сьогодні</code> / <code>завтра</code>",
                    $enLocaleId => "\xE2\x9C\x85 Message received.\n\n\xF0\x9F\x93\x85 <b>Step 3/3.</b> Enter broadcast date:\n<code>dd-mm-yyyy</code> or <code>today</code> / <code>tomorrow</code>",
                    $ruLocaleId => "\xE2\x9C\x85 Сообщение получено.\n\n\xF0\x9F\x93\x85 <b>Шаг 3/3.</b> Введите дату рассылки:\n<code>дд-мм-гггг</code> или <code>сегодня</code> / <code>завтра</code>",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_date_accepted',
                'description' => 'Broadcast: date accepted, enter time',
                'values' => [
                    $ukLocaleId => "\xE2\x9C\x85 Дата: <b>{date}</b>\n\n\xF0\x9F\x95\x90 <b>Крок 3/3.</b> Введіть час розсилки у форматі <code>гг:хх</code>:",
                    $enLocaleId => "\xE2\x9C\x85 Date: <b>{date}</b>\n\n\xF0\x9F\x95\x90 <b>Step 3/3.</b> Enter broadcast time in format <code>HH:MM</code>:",
                    $ruLocaleId => "\xE2\x9C\x85 Дата: <b>{date}</b>\n\n\xF0\x9F\x95\x90 <b>Шаг 3/3.</b> Введите время рассылки в формате <code>чч:мм</code>:",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_invalid_date',
                'description' => 'Broadcast: invalid date error',
                'values' => [
                    $ukLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F Невірна дата або дата в минулому.\nВведіть у форматі <code>дд-мм-рррр</code>, <code>сьогодні</code> або <code>завтра</code>.",
                    $enLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F Invalid date or date is in the past.\nEnter in format <code>dd-mm-yyyy</code>, <code>today</code> or <code>tomorrow</code>.",
                    $ruLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F Неверная дата или дата в прошлом.\nВведите в формате <code>дд-мм-гггг</code>, <code>сегодня</code> или <code>завтра</code>.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_invalid_time',
                'description' => 'Broadcast: invalid time format error',
                'values' => [
                    $ukLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F Невірний формат часу. Введіть у форматі <code>гг:хх</code>, наприклад: <code>14:30</code>",
                    $enLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F Invalid time format. Enter in format <code>HH:MM</code>, e.g.: <code>14:30</code>",
                    $ruLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F Неверный формат времени. Введите в формате <code>чч:мм</code>, например: <code>14:30</code>",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_time_past',
                'description' => 'Broadcast: time is in the past error',
                'values' => [
                    $ukLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F Вказаний час вже в минулому. Введіть інший час:",
                    $enLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F The specified time is in the past. Enter a different time:",
                    $ruLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F Указанное время уже в прошлом. Введите другое время:",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_invalid_message',
                'description' => 'Broadcast: invalid message type error',
                'values' => [
                    $ukLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F Надішліть текстове повідомлення або фото (з підписом або без).",
                    $enLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F Please send a text message or a photo (with or without caption).",
                    $ruLocaleId => "\xE2\x9A\xA0\xEF\xB8\x8F Отправьте текстовое сообщение или фото (с подписью или без).",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_preview',
                'description' => 'Broadcast: preview before saving',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x91\x81 <b>Перегляд розсилки</b>\n\n\xF0\x9F\x91\xA5 <b>Отримувачі:</b> {role}\n\xF0\x9F\x93\x85 <b>Дата і час:</b> {datetime}\n\xF0\x9F\x92\xAC <b>Тип:</b> {type}\n\n<b>Повідомлення:</b>\n{message}",
                    $enLocaleId => "\xF0\x9F\x91\x81 <b>Broadcast preview</b>\n\n\xF0\x9F\x91\xA5 <b>Recipients:</b> {role}\n\xF0\x9F\x93\x85 <b>Date and time:</b> {datetime}\n\xF0\x9F\x92\xAC <b>Type:</b> {type}\n\n<b>Message:</b>\n{message}",
                    $ruLocaleId => "\xF0\x9F\x91\x81 <b>Предпросмотр рассылки</b>\n\n\xF0\x9F\x91\xA5 <b>Получатели:</b> {role}\n\xF0\x9F\x93\x85 <b>Дата и время:</b> {datetime}\n\xF0\x9F\x92\xAC <b>Тип:</b> {type}\n\n<b>Сообщение:</b>\n{message}",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_saved',
                'description' => 'Broadcast: saved confirmation',
                'values' => [
                    $ukLocaleId => "\xE2\x9C\x85 <b>Розсилку збережено!</b>\n\n\xF0\x9F\x93\x85 Буде відправлено: <b>{datetime}</b>\n\xF0\x9F\x86\x94 ID розсилки: #{id}",
                    $enLocaleId => "\xE2\x9C\x85 <b>Broadcast saved!</b>\n\n\xF0\x9F\x93\x85 Will be sent: <b>{datetime}</b>\n\xF0\x9F\x86\x94 Broadcast ID: #{id}",
                    $ruLocaleId => "\xE2\x9C\x85 <b>Рассылка сохранена!</b>\n\n\xF0\x9F\x93\x85 Будет отправлено: <b>{datetime}</b>\n\xF0\x9F\x86\x94 ID рассылки: #{id}",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_edit_prompt',
                'description' => 'Broadcast: edit message prompt',
                'values' => [
                    $ukLocaleId => "\xE2\x9C\x8F\xEF\xB8\x8F Надішліть нове повідомлення для розсилки.\nМожна надіслати текст або фото (з підписом або без).",
                    $enLocaleId => "\xE2\x9C\x8F\xEF\xB8\x8F Send a new message for the broadcast.\nYou can send text or a photo (with or without caption).",
                    $ruLocaleId => "\xE2\x9C\x8F\xEF\xB8\x8F Отправьте новое сообщение для рассылки.\nМожно отправить текст или фото (с подписью или без).",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_cancelled',
                'description' => 'Broadcast: creation cancelled',
                'values' => [
                    $ukLocaleId => "\xE2\x9D\x8C Створення розсилки скасовано.",
                    $enLocaleId => "\xE2\x9D\x8C Broadcast creation cancelled.",
                    $ruLocaleId => "\xE2\x9D\x8C Создание рассылки отменено.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_use_buttons',
                'description' => 'Broadcast: prompt to use buttons instead of text',
                'values' => [
                    $ukLocaleId => "\xE2\x9D\x97 Натисніть кнопку вище для продовження, або /mmsg для скасування.",
                    $enLocaleId => "\xE2\x9D\x97 Use the buttons above to continue, or /mmsg to cancel.",
                    $ruLocaleId => "\xE2\x9D\x97 Нажмите кнопку выше для продолжения, или /mmsg для отмены.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_no_broadcasts',
                'description' => 'Broadcast: no scheduled broadcasts',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x93\xAD Запланованих розсилок немає.",
                    $enLocaleId => "\xF0\x9F\x93\xAD No scheduled broadcasts.",
                    $ruLocaleId => "\xF0\x9F\x93\xAD Запланированных рассылок нет.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.mmsg_deleted',
                'description' => 'Broadcast: broadcast deleted',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x97\x91 Розсилку <b>#{id}</b> видалено.",
                    $enLocaleId => "\xF0\x9F\x97\x91 Broadcast <b>#{id}</b> deleted.",
                    $ruLocaleId => "\xF0\x9F\x97\x91 Рассылка <b>#{id}</b> удалена.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.access_denied_admin',
                'description' => 'Access denied - admin only',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x9A\xAB Доступ заборонено. Тільки для адміністраторів.",
                    $enLocaleId => "\xF0\x9F\x9A\xAB Access denied. Admin only.",
                    $ruLocaleId => "\xF0\x9F\x9A\xAB Доступ запрещён. Только для администраторов.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.start_first',
                'description' => 'Prompt to start bot first',
                'values' => [
                    $ukLocaleId => 'Спочатку запустіть бота командою /start',
                    $enLocaleId => 'Please start the bot first using /start',
                    $ruLocaleId => 'Сначала запустите бота командой /start',
                ],
            ],

            // Broadcast role labels
            [
                'type' => 'label',
                'resource_key' => 'role.all',
                'description' => 'Role label: all users',
                'values' => [
                    $ukLocaleId => 'Всі',
                    $enLocaleId => 'All',
                    $ruLocaleId => 'Все',
                ],
            ],
            [
                'type' => 'label',
                'resource_key' => 'role.client',
                'description' => 'Role label: clients',
                'values' => [
                    $ukLocaleId => 'Клієнти',
                    $enLocaleId => 'Clients',
                    $ruLocaleId => 'Клиенты',
                ],
            ],
            [
                'type' => 'label',
                'resource_key' => 'role.employee',
                'description' => 'Role label: employees',
                'values' => [
                    $ukLocaleId => 'Співробітники',
                    $enLocaleId => 'Employees',
                    $ruLocaleId => 'Сотрудники',
                ],
            ],
            [
                'type' => 'label',
                'resource_key' => 'role.agent',
                'description' => 'Role label: agents',
                'values' => [
                    $ukLocaleId => 'Агенти',
                    $enLocaleId => 'Agents',
                    $ruLocaleId => 'Агенты',
                ],
            ],
            [
                'type' => 'label',
                'resource_key' => 'role.admin',
                'description' => 'Role label: admins',
                'values' => [
                    $ukLocaleId => 'Адміни',
                    $enLocaleId => 'Admins',
                    $ruLocaleId => 'Админы',
                ],
            ],

            // Broadcast type labels
            [
                'type' => 'label',
                'resource_key' => 'mmsg.type_photo',
                'description' => 'Broadcast type: photo with caption',
                'values' => [
                    $ukLocaleId => 'Фото з підписом',
                    $enLocaleId => 'Photo with caption',
                    $ruLocaleId => 'Фото с подписью',
                ],
            ],
            [
                'type' => 'label',
                'resource_key' => 'mmsg.type_text',
                'description' => 'Broadcast type: text',
                'values' => [
                    $ukLocaleId => 'Текст',
                    $enLocaleId => 'Text',
                    $ruLocaleId => 'Текст',
                ],
            ],

            // Broadcast buttons
            [
                'type' => 'button',
                'resource_key' => 'button.mmsg_all',
                'description' => 'Broadcast button: all recipients',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x91\xA5 Всі",
                    $enLocaleId => "\xF0\x9F\x91\xA5 All",
                    $ruLocaleId => "\xF0\x9F\x91\xA5 Все",
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.mmsg_clients',
                'description' => 'Broadcast button: clients',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x91\xA4 Клієнти",
                    $enLocaleId => "\xF0\x9F\x91\xA4 Clients",
                    $ruLocaleId => "\xF0\x9F\x91\xA4 Клиенты",
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.mmsg_employees',
                'description' => 'Broadcast button: employees',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x92\xBC Співробітники",
                    $enLocaleId => "\xF0\x9F\x92\xBC Employees",
                    $ruLocaleId => "\xF0\x9F\x92\xBC Сотрудники",
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.mmsg_agents',
                'description' => 'Broadcast button: agents',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x9B\xA0 Агенти",
                    $enLocaleId => "\xF0\x9F\x9B\xA0 Agents",
                    $ruLocaleId => "\xF0\x9F\x9B\xA0 Агенты",
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.mmsg_admins',
                'description' => 'Broadcast button: admins',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x91\x91 Адміни",
                    $enLocaleId => "\xF0\x9F\x91\x91 Admins",
                    $ruLocaleId => "\xF0\x9F\x91\x91 Админы",
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.mmsg_cancel',
                'description' => 'Broadcast button: cancel',
                'values' => [
                    $ukLocaleId => "\xE2\x9D\x8C Скасувати",
                    $enLocaleId => "\xE2\x9D\x8C Cancel",
                    $ruLocaleId => "\xE2\x9D\x8C Отменить",
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.mmsg_edit',
                'description' => 'Broadcast button: edit message',
                'values' => [
                    $ukLocaleId => "\xE2\x9C\x8F\xEF\xB8\x8F Редагувати повідомлення",
                    $enLocaleId => "\xE2\x9C\x8F\xEF\xB8\x8F Edit message",
                    $ruLocaleId => "\xE2\x9C\x8F\xEF\xB8\x8F Редактировать сообщение",
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.mmsg_save',
                'description' => 'Broadcast button: save broadcast',
                'values' => [
                    $ukLocaleId => "\xE2\x9C\x85 Зберегти розсилку",
                    $enLocaleId => "\xE2\x9C\x85 Save broadcast",
                    $ruLocaleId => "\xE2\x9C\x85 Сохранить рассылку",
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.mmsg_delete',
                'description' => 'Broadcast button: delete broadcast',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x97\x91 Видалити",
                    $enLocaleId => "\xF0\x9F\x97\x91 Delete",
                    $ruLocaleId => "\xF0\x9F\x97\x91 Удалить",
                ],
            ],

            // Project selection (for employees)
            [
                'type' => 'message',
                'resource_key' => 'messages.session_expired',
                'description' => 'Session expired message (e.g. when project selection keyboard is stale)',
                'values' => [
                    $ukLocaleId => '⏰ Сесія застаріла. Будь ласка, надішліть ваше повідомлення ще раз.',
                    $enLocaleId => '⏰ Session expired. Please send your message again.',
                    $ruLocaleId => '⏰ Сессия истекла. Пожалуйста, отправьте ваше сообщение ещё раз.',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.select_project',
                'description' => 'Prompt to select project for employee ticket',
                'values' => [
                    $ukLocaleId => '📂 Оберіть проект, до якого відноситься ваше звернення:',
                    $enLocaleId => '📂 Please select the project your request relates to:',
                    $ruLocaleId => '📂 Выберите проект, к которому относится ваше обращение:',
                ],
            ],

            // Error messages
            [
                'type' => 'message',
                'resource_key' => 'messages.processing_error',
                'description' => 'Processing error message',
                'values' => [
                    $ukLocaleId => 'Вибачте, виникла проблема з обробкою вашого запиту. Будь ласка, спробуйте ще раз через декілька хвилин.',
                    $enLocaleId => 'Sorry, there was a problem processing your request. Please try again in a few moments.',
                    $ruLocaleId => 'Извините, возникла проблема с обработкой вашего запроса. Пожалуйста, попробуйте еще раз через несколько минут.',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.please_wait',
                'description' => 'Please wait message',
                'values' => [
                    $ukLocaleId => 'Наша система зараз завантажена. Будь ласка, зачекайте хвилину і спробуйте ще раз.',
                    $enLocaleId => 'Our system is currently busy. Please wait a moment and try again.',
                    $ruLocaleId => 'Наша система сейчас загружена. Пожалуйста, подождите минуту и попробуйте еще раз.',
                ],
            ],

            // ===== AFTER-HOURS TEMPLATES =====

            // Generic after-hours auto-reply (fallback)
            [
                'type' => 'message',
                'resource_key' => 'messages.after_hours_reply',
                'description' => 'Auto-reply to client during non-working hours (fallback)',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x95\x90 Ваше повідомлення отримано. Зараз {currentTime}, наш робочий час починається {workTime}. Ми відповімо вам якнайшвидше.",
                    $enLocaleId => "\xF0\x9F\x95\x90 Your message has been received. It is currently {currentTime}, our working hours start at {workTime}. We will respond as soon as possible.",
                    $ruLocaleId => "\xF0\x9F\x95\x90 Ваше сообщение получено. Сейчас {currentTime}, наше рабочее время начинается в {workTime}. Мы ответим вам как можно скорее.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.after_hours_reply.monday',
                'description' => 'After-hours reply — Monday',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x95\x90 Ваше повідомлення отримано. Зараз {currentTime}, наш робочий час починається {workTime}. Ми відповімо вам якнайшвидше.",
                    $enLocaleId => "\xF0\x9F\x95\x90 Your message has been received. It is currently {currentTime}, our working hours start at {workTime}. We will respond as soon as possible.",
                    $ruLocaleId => "\xF0\x9F\x95\x90 Ваше сообщение получено. Сейчас {currentTime}, наше рабочее время начинается в {workTime}. Мы ответим вам как можно скорее.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.after_hours_reply.tuesday',
                'description' => 'After-hours reply — Tuesday',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x95\x90 Ваше повідомлення отримано. Зараз {currentTime}, наш робочий час починається {workTime}. Ми відповімо вам якнайшвидше.",
                    $enLocaleId => "\xF0\x9F\x95\x90 Your message has been received. It is currently {currentTime}, our working hours start at {workTime}. We will respond as soon as possible.",
                    $ruLocaleId => "\xF0\x9F\x95\x90 Ваше сообщение получено. Сейчас {currentTime}, наше рабочее время начинается в {workTime}. Мы ответим вам как можно скорее.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.after_hours_reply.wednesday',
                'description' => 'After-hours reply — Wednesday',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x95\x90 Ваше повідомлення отримано. Зараз {currentTime}, наш робочий час починається {workTime}. Ми відповімо вам якнайшвидше.",
                    $enLocaleId => "\xF0\x9F\x95\x90 Your message has been received. It is currently {currentTime}, our working hours start at {workTime}. We will respond as soon as possible.",
                    $ruLocaleId => "\xF0\x9F\x95\x90 Ваше сообщение получено. Сейчас {currentTime}, наше рабочее время начинается в {workTime}. Мы ответим вам как можно скорее.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.after_hours_reply.thursday',
                'description' => 'After-hours reply — Thursday',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x95\x90 Ваше повідомлення отримано. Зараз {currentTime}, наш робочий час починається {workTime}. Ми відповімо вам якнайшвидше.",
                    $enLocaleId => "\xF0\x9F\x95\x90 Your message has been received. It is currently {currentTime}, our working hours start at {workTime}. We will respond as soon as possible.",
                    $ruLocaleId => "\xF0\x9F\x95\x90 Ваше сообщение получено. Сейчас {currentTime}, наше рабочее время начинается в {workTime}. Мы ответим вам как можно скорее.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.after_hours_reply.friday',
                'description' => 'After-hours reply — Friday',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x95\x90 Ваше повідомлення отримано. Зараз {currentTime}, наш робочий час починається {workTime}. Ми відповімо вам якнайшвидше.",
                    $enLocaleId => "\xF0\x9F\x95\x90 Your message has been received. It is currently {currentTime}, our working hours start at {workTime}. We will respond as soon as possible.",
                    $ruLocaleId => "\xF0\x9F\x95\x90 Ваше сообщение получено. Сейчас {currentTime}, наше рабочее время начинается в {workTime}. Мы ответим вам как можно скорее.",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.after_hours_reply.saturday',
                'description' => 'After-hours reply — Saturday',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x95\x90 Ваше повідомлення отримано. Сьогодні вихідний, наш робочий час починається {workTime}. Гарних вихідних!",
                    $enLocaleId => "\xF0\x9F\x95\x90 Your message has been received. Today is a day off, our working hours start at {workTime}. Have a nice weekend!",
                    $ruLocaleId => "\xF0\x9F\x95\x90 Ваше сообщение получено. Сегодня выходной, наше рабочее время начинается в {workTime}. Хороших выходных!",
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.after_hours_reply.sunday',
                'description' => 'After-hours reply — Sunday',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x95\x90 Ваше повідомлення отримано. Сьогодні вихідний, наш робочий час починається {workTime}. Гарних вихідних!",
                    $enLocaleId => "\xF0\x9F\x95\x90 Your message has been received. Today is a day off, our working hours start at {workTime}. Have a nice weekend!",
                    $ruLocaleId => "\xF0\x9F\x95\x90 Ваше сообщение получено. Сегодня выходной, наше рабочее время начинается в {workTime}. Хороших выходных!",
                ],
            ],

            // Agent prompt about reminder
            [
                'type' => 'message',
                'resource_key' => 'messages.after_hours_agent_prompt',
                'description' => 'Prompt for agent when replying during non-working hours',
                'values' => [
                    $ukLocaleId => "\xF0\x9F\x92\xAC Клієнту вже відповіли в неробочий час. Нагадати ще раз у робочий час?",
                    $enLocaleId => "\xF0\x9F\x92\xAC Client was already replied to during non-working hours. Remind again at work hours?",
                    $ruLocaleId => "\xF0\x9F\x92\xAC Клиенту уже ответили в нерабочее время. Напомнить ещё раз в рабочее время?",
                ],
            ],

            // Reminder text for agents
            [
                'type' => 'message',
                'resource_key' => 'messages.after_hours_reminder',
                'description' => 'Reminder sent to agents when work hours start',
                'values' => [
                    $ukLocaleId => 'Клієнт писав у неробочий час, перевірте.',
                    $enLocaleId => 'Client wrote during non-working hours, please check.',
                    $ruLocaleId => 'Клиент писал в нерабочее время, проверьте.',
                ],
            ],

            // Day name translations
            [
                'type' => 'message',
                'resource_key' => 'day.monday',
                'description' => 'Monday short name',
                'values' => [
                    $ukLocaleId => 'Пн',
                    $enLocaleId => 'Mon',
                    $ruLocaleId => 'Пн',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'day.tuesday',
                'description' => 'Tuesday short name',
                'values' => [
                    $ukLocaleId => 'Вт',
                    $enLocaleId => 'Tue',
                    $ruLocaleId => 'Вт',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'day.wednesday',
                'description' => 'Wednesday short name',
                'values' => [
                    $ukLocaleId => 'Ср',
                    $enLocaleId => 'Wed',
                    $ruLocaleId => 'Ср',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'day.thursday',
                'description' => 'Thursday short name',
                'values' => [
                    $ukLocaleId => 'Чт',
                    $enLocaleId => 'Thu',
                    $ruLocaleId => 'Чт',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'day.friday',
                'description' => 'Friday short name',
                'values' => [
                    $ukLocaleId => 'Пт',
                    $enLocaleId => 'Fri',
                    $ruLocaleId => 'Пт',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'day.saturday',
                'description' => 'Saturday short name',
                'values' => [
                    $ukLocaleId => 'Сб',
                    $enLocaleId => 'Sat',
                    $ruLocaleId => 'Сб',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'day.sunday',
                'description' => 'Sunday short name',
                'values' => [
                    $ukLocaleId => 'Нд',
                    $enLocaleId => 'Sun',
                    $ruLocaleId => 'Вс',
                ],
            ],

            // ─── Close keyword suggestion ───────────────────────────────────

            [
                'type' => 'config',
                'resource_key' => 'config.close_keywords',
                'description' => 'Keywords that trigger a close-ticket suggestion (JSON array, per locale)',
                'values' => [
                    $ukLocaleId => '["дякую","дякуємо","до побачення","вирішено","зрозуміло","все добре","дякую за допомогу","проблему вирішено","дякую велике"]',
                    $enLocaleId => '["thanks","thank you","bye","goodbye","solved","got it","all good","problem solved","issue resolved","many thanks"]',
                    $ruLocaleId => '["спасибо","благодарю","до свидания","всё","понятно","проблема решена","спасибо за помощь","вопрос решён","большое спасибо","всё хорошо"]',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.close_suggestion',
                'description' => 'Suggestion to close the ticket after a thankful message',
                'values' => [
                    $ukLocaleId => '✅ Схоже, питання вирішено. Бажаєте закрити звернення?',
                    $enLocaleId => '✅ It seems your issue has been resolved. Would you like to close this ticket?',
                    $ruLocaleId => '✅ Похоже, вопрос решён. Хотите закрыть обращение?',
                ],
            ],
            [
                'type' => 'message',
                'resource_key' => 'messages.close_suggest_declined',
                'description' => 'Response when client declines the close suggestion',
                'values' => [
                    $ukLocaleId => '💬 Добре! Якщо виникнуть запитання — ми тут.',
                    $enLocaleId => '💬 No problem! We are still here if you need anything.',
                    $ruLocaleId => '💬 Хорошо! Если возникнут вопросы — мы здесь.',
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.close_suggest_yes',
                'description' => 'Yes, close ticket button in close suggestion',
                'values' => [
                    $ukLocaleId => '✅ Так, закрити',
                    $enLocaleId => '✅ Yes, close',
                    $ruLocaleId => '✅ Да, закрыть',
                ],
            ],
            [
                'type' => 'button',
                'resource_key' => 'button.close_suggest_no',
                'description' => 'No, continue button in close suggestion',
                'values' => [
                    $ukLocaleId => '💬 Ні, продовжити',
                    $enLocaleId => '💬 No, continue',
                    $ruLocaleId => '💬 Нет, продолжить',
                ],
            ],
        ];

        foreach ($resources as $resource) {
            foreach ($resource['values'] as $localeId => $value) {
                DB::table('bot_resources')->updateOrInsert(
                    [
                        'resource_key' => $resource['resource_key'],
                        'locale_id' => $localeId,
                    ],
                    [
                        'type' => $resource['type'],
                        'resource_value' => $value,
                        'description' => $resource['description'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
