<?php

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,

    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'div,b,strong,i,em,u,a[href|title],ul,ol,li,p[style],br,span[style],img[width|height|alt|src]',
            'CSS.AllowedProperties' => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty' => true,
        ],

        /*
         * What the editor's toolbar can actually produce, and nothing else.
         * The list is deliberately short: every tag here is one a person
         * writing a job description reaches for, and anything outside it --
         * scripts, styles, iframes, event handlers, an img pulled from
         * somewhere else -- has no business in a field a stranger fills in.
         *
         * Links keep only href, and only to schemes that go somewhere a
         * reader expects; javascript: and data: are not among them.
         */
        'richtext' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,em,ul,ol,li,h3,h4,a[href|title]',
            'HTML.TargetBlank' => true,
            'HTML.Nofollow' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
        ],
    ],
];
