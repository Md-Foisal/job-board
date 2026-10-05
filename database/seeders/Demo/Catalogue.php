<?php

namespace Database\Seeders\Demo;

use InvalidArgumentException;

/**
 * Hand-written content for a seeded database that reads like a real job
 * board: categories, skills, employers, the roles they hire for and the
 * people who apply. Factories stay random on purpose -- tests depend on
 * them -- so anything a person is meant to look at comes from here.
 */
final class Catalogue
{
    public const CATEGORIES = [
        'Engineering',
        'Information Technology',
        'Data & Analytics',
        'Design',
        'Product',
        'Marketing',
        'Sales',
        'Customer Support',
        'Finance',
        'Operations',
        'Human Resources',
        'Writing & Content',
    ];

    /**
     * A name that is also an everyday word is made specific ("Microsoft
     * Excel", not "Excel"; "Manual testing", not "Testing"), because the
     * CV reader finds skills by name in free text.
     */
    public const SKILLS = [
        // Software
        'PHP', 'Laravel', 'JavaScript', 'TypeScript', 'React', 'Vue.js', 'Node.js', 'Python', 'Go', 'Kotlin',
        'MySQL', 'PostgreSQL', 'HTML', 'CSS', 'Tailwind CSS', 'Git', 'Docker', 'AWS', 'Linux', 'REST APIs',
        'CI/CD', 'Automated testing', 'Manual testing', 'Playwright',
        // IT
        'Microsoft 365', 'Active Directory', 'Network administration', 'Technical support', 'Troubleshooting',
        // Data
        'SQL', 'Microsoft Excel', 'Power BI', 'Tableau', 'Data analysis', 'Data visualization',
        // Design and product
        'Figma', 'UI design', 'UX research', 'Prototyping', 'Design systems', 'Adobe Illustrator',
        'Product management', 'Project management', 'User stories', 'Roadmapping', 'Jira',
        // Marketing, writing and sales
        'SEO', 'Google Analytics', 'Content marketing', 'Email marketing', 'Social media marketing', 'Paid advertising',
        'Copywriting', 'Editing', 'Proofreading', 'Technical writing',
        'B2B sales', 'Lead generation', 'Account management', 'Negotiation', 'Salesforce', 'HubSpot',
        // Support, finance, operations and people
        'Customer service', 'Zendesk', 'Intercom', 'Live chat support',
        'Accounting', 'Bookkeeping', 'Xero', 'QuickBooks', 'Payroll', 'Financial reporting', 'Financial modeling',
        'Logistics', 'Supply chain management', 'Vendor management', 'Office administration', 'Event planning',
        'Recruiting', 'Onboarding', 'Employee relations', 'HR policy',
    ];

    /** @var array<string, array<mixed>> */
    private static array $loaded = [];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function roles(): array
    {
        return self::load('roles');
    }

    /**
     * @return array<string, mixed>
     */
    public static function role(string $key): array
    {
        return self::roles()[$key] ?? throw new InvalidArgumentException("The demo catalogue has no role called {$key}.");
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function companies(): array
    {
        return self::load('companies');
    }

    /**
     * @return array<string, mixed>
     */
    public static function people(): array
    {
        return self::load('people');
    }

    /**
     * @return array<string, mixed>
     */
    public static function demo(): array
    {
        return self::load('demo');
    }

    /**
     * @return array<string, mixed>
     */
    public static function place(string $key): array
    {
        return self::people()['places'][$key] ?? throw new InvalidArgumentException("The demo catalogue has no place called {$key}.");
    }

    /**
     * A full name as people from that place commonly have it.
     */
    public static function personName(string $place): string
    {
        $place = self::place($place);

        return $place['first'][array_rand($place['first'])].' '.$place['last'][array_rand($place['last'])];
    }

    /**
     * @return array<mixed>
     */
    private static function load(string $name): array
    {
        return self::$loaded[$name] ??= require __DIR__."/data/{$name}.php";
    }
}
