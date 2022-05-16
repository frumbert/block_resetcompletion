### Reset Completion Block
This plugin lets you reset the completion data for a course for the logged in user WHEN logged in as another user - e.g. admin logs in as the user.

It will blindly delete records from course, module, quiz, lesson, scorm and certificate tables.

### Based on:

https://moodle.org/plugins/local_recompletion


#### Differences to original:

- Only handles completion, choice, scorm, quiz, lessons, certificate tables as no other tables were required at the time
- Only appears if the user is LOGGED IN AS someone else
- Doesn't care if the course isn't yet completed

