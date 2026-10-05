<?php

use Database\Seeders\Demo\Catalogue;

test('every role names a seeded category, seeded skills and a family of people', function () {
    $families = array_keys(Catalogue::people()['families']);

    foreach (Catalogue::roles() as $key => $role) {
        expect($role['category'])->toBeIn(Catalogue::CATEGORIES, "{$key}: category")
            ->and($role['family'])->toBeIn($families, "{$key}: family");

        foreach ([...$role['required'], ...$role['nice']] as $skill) {
            expect($skill)->toBeIn(Catalogue::SKILLS, "{$key}: skill {$skill}");
        }
    }
});

test('every company hires for roles that exist and comes from a known place', function () {
    foreach (Catalogue::companies() as $slug => $company) {
        expect(array_key_exists($company['locale'], Catalogue::people()['places']))->toBeTrue("{$slug}: place");

        foreach (array_keys($company['roles']) as $role) {
            expect(array_key_exists($role, Catalogue::roles()))->toBeTrue("{$slug}: role {$role}");
        }
    }
});

test('the skills people are given and the demo candidate lists are all seeded', function () {
    foreach (Catalogue::people()['families'] as $key => $family) {
        expect($family['titles'])->toHaveCount(3, "{$key}: titles");

        foreach ([...$family['skills'], ...$family['extra']] as $skill) {
            expect($skill)->toBeIn(Catalogue::SKILLS, "{$key}: skill {$skill}");
        }
    }

    foreach (array_keys(Catalogue::demo()['candidate']['skills']) as $skill) {
        expect($skill)->toBeIn(Catalogue::SKILLS, "demo candidate: skill {$skill}");
    }
});

test('skill and category names are unique regardless of case', function () {
    expect(array_unique(array_map('strtolower', Catalogue::SKILLS)))->toHaveCount(count(Catalogue::SKILLS))
        ->and(array_unique(array_map('strtolower', Catalogue::CATEGORIES)))->toHaveCount(count(Catalogue::CATEGORIES));
});
