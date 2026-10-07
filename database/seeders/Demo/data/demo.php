<?php

/*
 * The company and the people behind the demo accounts. Written out by
 * hand rather than generated, because these are the pages someone looks
 * at first.
 */

return [
    'company' => [
        'name' => 'Fernhill Software',
        'industry' => 'Healthcare software',
        'size' => '11-50',
        'identity' => 'company',
        'city' => 'London',
        'country' => 'United Kingdom',
        'timezone' => 'Europe/London',
        'currency' => 'GBP',
        'pay' => 0.72,
        'period' => 'yearly',
        'workplace' => 'hybrid',
        'pitch' => 'Fernhill Software makes online booking and reminders for independent clinics, from physiotherapists to dentists.',
        'about' => '<p>Fernhill Software makes online booking, reminders and payments for independent clinics across the UK. Physiotherapists, dentists and osteopaths use it to fill their diaries and cut no-shows.</p><p>We are a team of thirty in London, most of us in the office two or three days a week. We answer every customer within a working day, and we expect the same care in how we treat applicants.</p>',
        'benefits' => [
            '28 days of holiday plus bank holidays',
            'Hybrid working from our office near Old Street',
            'Employer pension contribution of 5%',
            'A learning budget of £1,000 a year',
        ],
    ],

    'staff' => [
        'superadmin' => 'Priya Nair',
        'moderator' => 'Tom Becker',
    ],

    'employer' => [
        'name' => 'Hannah Lewis',
        'job_title' => 'Head of Operations',
        'recruiter_bio' => 'I run operations at Fernhill and hire for our support and office teams. I read every application myself and reply within a week, whatever the answer.',
    ],

    'candidate' => [
        'name' => 'Rafi Ahmed',
        'headline' => 'Full Stack Developer (Laravel, Vue)',
        'location' => 'London, United Kingdom',
        'portfolio_url' => 'https://rafiahmed.example',
        'bio' => 'Full stack developer with five years of experience building web products in Laravel and Vue. I enjoy owning a feature from the first conversation with a customer to the release, and I care about accessible interfaces and well-tested code. I am looking for a product team where I can grow towards a senior role.',
        'skills' => [
            'Laravel' => 'advanced',
            'PHP' => 'advanced',
            'Vue.js' => 'advanced',
            'JavaScript' => 'advanced',
            'MySQL' => 'intermediate',
            'Tailwind CSS' => 'intermediate',
            'TypeScript' => 'intermediate',
            'Git' => 'advanced',
            'Docker' => 'beginner',
        ],
        'experience' => [
            [
                'company' => 'Granite Peak Software',
                'title' => 'Full Stack Developer',
                'start' => 30,
                'end' => null,
                'description' => '<ul><li>Build features across Laravel, Vue and MySQL for a scheduling product used by around 3,000 small businesses</li><li>Led the move from jQuery pages to Vue components, one screen at a time without a freeze on new work</li><li>Introduced feature tests for billing, which caught two pricing bugs before release</li></ul>',
            ],
            [
                'company' => 'Blue Harbour Digital',
                'title' => 'Web Developer',
                'start' => 62,
                'end' => 31,
                'description' => '<ul><li>Built Laravel websites and customer portals for agency clients in retail and travel</li><li>Worked directly with clients to turn requests into small, clear tasks</li><li>Set up automated deployments, which ended the Friday-evening manual releases</li></ul>',
            ],
        ],
        // Example addresses only: a real GitHub or certificate link would
        // point at somebody who exists.
        'projects' => [
            [
                'name' => 'Shiftboard',
                'description' => '<p>An open-source rota planner for small cafés: drag shifts onto a week, swap them by text message. Laravel, Livewire and SQLite.</p>',
                'url' => 'https://shiftboard.rafiahmed.example',
                'source_url' => 'https://git.rafiahmed.example/shiftboard',
                'start' => 14,
                'end' => null,
            ],
            [
                'name' => 'Laravel bulk mailer',
                'description' => '<p>A small package that queues newsletters in batches and retries the ones that bounce softly.</p>',
                'url' => null,
                'source_url' => 'https://git.rafiahmed.example/bulk-mailer',
                'start' => 40,
                'end' => 34,
            ],
        ],
        'certifications' => [
            [
                'name' => 'AWS Certified Cloud Practitioner',
                'issuer' => 'Amazon Web Services',
                'issued' => 20,
                'expires' => -16,
                'credential_id' => 'AWS-CCP-0000',
                'credential_url' => 'https://certificates.rafiahmed.example/aws-ccp',
            ],
        ],
        'education' => [
            'institution' => 'University of Greenwich',
            'degree' => 'BSc',
            'field' => 'Computer Science',
            'start' => 98,
            'end' => 63,
        ],
        'preference' => [
            'min' => 4200,
            'max' => 5200,
            'currency' => 'GBP',
            'workplace' => 'hybrid',
            'employment' => 'full-time',
        ],
    ],

    'deleted' => 'Jonas Weber',
];
