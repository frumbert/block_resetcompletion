### Reset Completion Block
This plugin lets you reset the completion data for a course for a user. This block will only appear/function:

- When logged in AS THE USER
- When logged in as a SITE ADMIN

It will blindly delete records from course, module, quiz, lesson, scorm, grades and certificate tables. It writes a standard log record stating that it has been done/who by. It does not remove any existing log records.

### Based on:

https://moodle.org/plugins/local_recompletion


#### Differences to original:

- Only handles completion, choice, scorm, quiz, lessons, certificate tables as no other tables were required at the time
- Doesn't care if the course isn't yet completed
- Has a form for searching the currently enrolled users

###  Changes

- September 2024: Added search, ability for admin to delete users
- May 2023: Updated version
- May 2022: Created
