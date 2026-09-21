<x-static-page :heading="__('Privacy Policy')">
    <x-prose-section heading="What we collect">
        <p>
            When you create an account we store your name, email address and
            password (hashed, never in readable form). If you build a candidate
            profile we store what you enter: headline, bio, links, education and
            work history, skills, salary and work-type preferences, and any
            documents you upload such as a CV. If you post jobs, we store your
            company details and the postings themselves.
        </p>
        <p>
            We also record which job postings you open while signed in, so your
            dashboard can show you what you were last looking at.
        </p>
    </x-prose-section>

    <x-prose-section heading="Who can see it">
        <p>
            Your candidate profile and your uploaded documents are private until
            you apply for a job. When you apply, the company you applied to can
            see your profile and the CV you attached to that application -- nobody
            else. Documents are served through an access check every time, so a
            file address alone is not enough to open it.
        </p>
        <p>
            Job postings and company profiles are public, and are indexed by search
            engines. Your candidate profile is not.
        </p>
    </x-prose-section>

    <x-prose-section heading="Keeping and deleting it">
        <p>
            You can delete your account from your account settings. Deletion is
            not instant: the account is switched off first and can be restored for
            {{ \App\Models\User::DELETION_GRACE_DAYS }} days. After that we erase your
            personal data for good &mdash; your name, email address, profile, photos,
            uploaded files, cover letters and answers &mdash; and keep only an anonymous
            record that an application was made, so employers' and our own numbers
            stay correct. Until then, applications you have already submitted keep
            the copy of the CV you attached at the time.
        </p>
    </x-prose-section>

    <x-prose-section heading="What we do not do">
        <p>
            We do not sell your data, and we do not share it with advertisers.
            Email from us is limited to what the service needs: account and
            verification mail, and updates about applications you filed or received.
        </p>
    </x-prose-section>
</x-static-page>
