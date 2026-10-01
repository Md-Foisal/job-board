<x-static-page :heading="__('Privacy Policy')">
    <x-prose-section heading="What we collect">
        <p>
            When you create an account we store your name, email address and
            password (hashed, never in readable form). If you build a candidate
            profile we store what you enter: headline, bio, links, phone number
            and location, education and work history, skills, salary and
            work-type preferences, and any documents you upload or build, such as
            a CV. If you post jobs, we store your company details and the
            postings themselves.
        </p>
        <p>
            We also record which job postings you open while signed in, so your
            dashboard can show you what you were last looking at.
        </p>
        <p>
            Whenever anyone opens a job posting, signed in or not, we add one to
            that posting's view count for the day, which the company that posted
            it can see. The count holds no name, account or address. So that
            the same visit is not counted twice, your browser session remembers
            which postings it has already been counted for that day; nothing
            more is kept.
        </p>
    </x-prose-section>

    <x-prose-section heading="Who can see it">
        <p>
            Your candidate profile and your uploaded documents are private until
            you apply for a job. When you apply, the company you applied to can
            see your profile and the CV you attached to that application -- nobody
            else. Documents are served through an access check every time, so a
            file address alone is not enough to open it. Your salary and work-type
            preferences are never shown to employers, even when you apply, and
            neither are the phone number and location on your profile: they
            appear only on a CV you build from your profile and choose to send.
            The exceptions are a CV you ask our AI to read, and the parts of your
            profile you ask our AI to explain a match with or to improve the
            wording of: these are sent to Anthropic, as described below.
        </p>
        <p>
            Job postings and company profiles are public, and are indexed by search
            engines. Your candidate profile is not.
        </p>
    </x-prose-section>

    <x-prose-section heading="Filling your profile from your CV">
        <p>
            When you fill your profile from a CV in your library, we first read it
            ourselves, on our own servers: we look for skills from our list and for
            your LinkedIn, GitHub and portfolio links. Nothing is sent anywhere for
            this.
        </p>
        <p>
            If your plan includes it, you can also ask our AI to read the CV, to
            suggest your headline, summary, phone number, location, roles and
            education. Only then, and
            only that CV, is sent to Anthropic, which reads it on our behalf as our
            processor: a PDF is sent as the file, a Word file as its text. The
            suggestions come back to us and wait an hour for you to choose from;
            nothing is added to your profile unless you tick it. We keep a record
            that a reading happened, to count it against your plan's allowance,
            but not what the CV or the suggestions said.
        </p>
        <p>
            Anthropic does not use what we send it to train its models
            (<a href="https://privacy.claude.com/en/articles/7996868" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Anthropic: model training</a>).
            By default it deletes it within 30 days. If its automated safety systems
            flag it as breaking Anthropic's Usage Policy, it may keep it for up to
            two years
            (<a href="https://privacy.claude.com/en/articles/7996866" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Anthropic: data retention</a>).
        </p>
    </x-prose-section>

    <x-prose-section heading="How you match a job">
        <p>
            On a job's page we compare the job with your skills, work history and
            job preferences, on our own servers, and show the result to you alone.
            An employer sees only how many of the job's skills you have, never
            your preferences or how the rest compares. Nothing is sent anywhere
            for this.
        </p>
        <p>
            If your plan includes it, you can also ask our AI to explain the match
            in words. Only then is the following sent to Anthropic, which reads it
            on our behalf as our processor: the job posting, your headline, about
            text, skills, work history and education, and our own comparison. Your
            name, contact details, photos, links, CVs and salary expectations are
            not sent. The explanation is shown only to you, never to employers, and
            never changes your match or where you appear. We keep it for a day, so
            you can come back to it, and then delete it; we keep a record that one
            was made, to count it against your plan's allowance, but not what it
            said. Anthropic handles what we send the same way as a CV it reads,
            above.
        </p>
    </x-prose-section>

    <x-prose-section heading="Building a CV">
        <p>
            The CV builder makes a CV from your profile on our own servers.
            Nothing is sent anywhere for this. Your salary and work-type
            preferences are never on it, and your phone number and location
            appear only on CVs you build. A CV you save goes into your documents
            like one you upload, where your {{ \App\Support\DocumentUploads::RECENT_CVS_KEPT }} most recent CVs are kept;
            downloading gives you the file without keeping a copy.
        </p>
        <p>
            If your plan includes it, you can also ask our AI to suggest better
            wording. Only then is the following sent to Anthropic, which reads it
            on our behalf as our processor: your headline, summary, roles with
            their descriptions, education and skills. Your name, email address,
            phone number, location, links, photo and preferences are not sent.
            The suggestions wait an hour for you to choose from and are then
            deleted. Only the ones you tick are written to your profile, which is
            what an employer sees when you apply, so check that each one is true
            before you apply it. We keep a record that suggestions were made, to
            count them against your plan's allowance, but not what they said.
            Anthropic handles what we send the same way as a CV it reads, above.
        </p>
    </x-prose-section>

    <x-prose-section heading="Keeping and deleting it">
        <p>
            You can delete your account from your account settings. Deletion is
            not instant: the account is switched off first, and signing in again
            within {{ \App\Models\User::DELETION_GRACE_DAYS }} days restores it. After that we erase your
            personal data for good &mdash; your name, email address, profile, photos,
            uploaded and built files, cover letters and answers &mdash; and keep only an anonymous
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
