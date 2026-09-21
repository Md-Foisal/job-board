<?php

/*
 * Only the lines this app words differently. Laravel merges this over its
 * own validation file, so every other message stays the framework's.
 */
return [

    // What someone sees when a file never arrived -- most often because it
    // was over the server's size limit, which stops it before any of our
    // own size rules can say so.
    'uploaded' => 'The :attribute could not be uploaded. It may be larger than we accept — check the size limit shown with the field.',

    'attributes' => [
        // Livewire names the field after the component property.
        'newResume' => 'CV',
    ],

];
