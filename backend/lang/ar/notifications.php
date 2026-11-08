<?php

/*
 * نصوص الإشعارات (docs/NOTIFICATION_EVENTS.md). المتغيرات: :business و:queue
 * و:number و:ahead.
 */
return [
    'ticket_almost_turn' => [
        'title' => 'اقترب دورك',
        'body' => ':business، التذكرة :number: عدد من أمامك :ahead.',
        'body_next' => ':business، التذكرة :number: أنت التالي.',
    ],
    'ticket_called' => [
        'title' => 'حان دورك',
        'body' => ':business، نُوديت التذكرة :number. يرجى التوجه إلى المكتب.',
    ],
    'ticket_service_started' => [
        'title' => 'بدأت خدمتك',
        'body' => ':business، تتم الآن خدمة التذكرة :number.',
    ],
    'ticket_completed' => [
        'title' => 'اكتملت الزيارة',
        'body' => ':business، اكتملت التذكرة :number. شكرًا لك.',
    ],
    'ticket_cancelled' => [
        'title' => 'أُلغيت التذكرة',
        'body' => 'ألغى :business التذكرة :number.',
    ],
    'ticket_skipped' => [
        'title' => 'تم تخطي دورك',
        'body' => 'تخطى :business التذكرة :number.',
    ],
    'ticket_no_show' => [
        'title' => 'لم تكن حاضرًا',
        'body' => 'نادى :business التذكرة :number ولم تكن حاضرًا.',
    ],
    'queue_paused' => [
        'title' => 'توقف الدور مؤقتًا',
        'body' => 'أوقف :business الدور :queue مؤقتًا. تحتفظ التذكرة :number بمكانها.',
    ],
    'queue_closed' => [
        'title' => 'أُغلق الدور',
        'body' => 'أغلق :business الدور :queue. لم تعد التذكرة :number صالحة.',
    ],
    'staff_ticket_joined' => [
        'title' => 'عميل جديد',
        'body' => 'انضمت التذكرة :number إلى :queue.',
    ],
    'staff_ticket_cancelled' => [
        'title' => 'ألغى عميل تذكرته',
        'body' => 'غادرت التذكرة :number الدور :queue.',
    ],
];
