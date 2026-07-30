# Localized SCORM 1.2 generator

The administration provides one independent SCORM package per course and language. Each course must contain exactly one `Video` entity. English, French and German MP4 files and titles are stored independently.

## Requirements

- PHP 8.2 or newer
- PHP `zip` and `dom` extensions
- write access to `public/uploads/scorm` and `var/cache`
- web server and PHP upload limits compatible with the expected MP4 size

The generated package does not use a CDN, external JavaScript library or remote video service.

## Database migration

Run the migration after deployment:

```powershell
php bin/console doctrine:migrations:migrate
```

The migration adds these nullable columns to `video`:

```text
scorm_en
scorm_fr
scorm_de
scorm_title_en
scorm_title_fr
scorm_title_de
```

The existing `url` and `url_fr` fields are not changed.

## Administration workflow

Open **SCORM Generator** in the administration sidebar or visit:

```text
GET /admin/scorm
```

Select a course. The page displays English, French and German cards. Each card contains:

- a localized title
- an MP4 upload or replacement field
- a readiness status
- a language-specific ZIP download button

An export becomes available only when both the localized title and local MP4 file exist. There is no fallback between languages.

Uploaded files are stored under:

```text
public/uploads/scorm/{courseId}/{videoId}/video-{language}-{random}.mp4
```

## Configuration

```yaml
parameters:
    app.scorm.max_package_size: 5153960755
    app.scorm.completion_threshold: 90
    app.scorm.commit_interval_seconds: 10
```

The 5,153,960,755-byte internal limit is approximately 4.8 GiB and keeps a margin below the LMS 5 GB limit.

## Package content

```text
imsmanifest.xml
index.html
assets/css/app.css
assets/js/scorm-api.js
assets/js/course.js
data/course.json
videos/video-01.mp4
```

`imsmanifest.xml` is located directly at the ZIP root. The selected localized title is used as the manifest title, course title and only video title. MP4 data is stored without ZIP compression.

## Command-line generator

The standalone command remains available for technical validation with arbitrary local MP4 files:

```powershell
php bin/console app:scorm:generate-test `
  --title="Test course" `
  --identifier="test-course-001" `
  --video="C:\media\video.mp4" `
  --output="C:\temp\test-course-scorm.zip"
```

## Validation commands

```powershell
php bin/phpunit
php bin/console lint:container
php bin/console lint:twig templates/admin/scorm/index.html.twig
node --check resources/scorm/assets/js/scorm-api.js
node --check resources/scorm/assets/js/course.js
node --test tests/Scorm/scorm-api.test.js
```

## LMS checklist

1. Confirm that `imsmanifest.xml` is at the ZIP root.
2. Import the ZIP as SCORM 1.2 content.
3. Confirm that the interface and system messages are in English.
4. Start the video, close the SCO and verify the restored playback position.
5. Seek near the end and verify that the course is not completed by seeking alone.
6. Watch at least 90% of the video and verify the `completed` status.
7. Verify session time, pause saves, hidden-page saves and window-close saves.
8. Test the package in Chrome, Edge, Firefox and Safari.

## Operational limits

- SCORM 1.2 only
- one SCO and one video per package
- one package language at a time
- no quiz
- no remote MP4 download
- synchronous ZIP generation

Large browser uploads require matching `upload_max_filesize`, `post_max_size`, proxy body-size limits and request timeouts.
