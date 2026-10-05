# 6. Moments in UTC, calendar dates as they are

Date: October 2026 (layer 7)
Status: Accepted

## Context

People and companies on the board live in different time zones. Before this decision a time of
11:41 pm in Dhaka was shown as 5:41 pm, the UTC time. A job alert sent at 8 am UTC reaches Dhaka in the
afternoon. A closing date of 15 March means the end of 15 March for the company, not for the server.
And on SQLite dates are stored as text, so a column must always be written in one format.

## Decision

- A moment (something that happened at an instant) is stored in UTC and converted to the viewer's
  zone only when it is shown.
- A calendar date (a start date, "available from", the day of a daily count) is never converted.
- Each person has a time zone, taken from the browser until they pick one in settings.
- Each company has a reporting zone. A closing date ends at the end of that day in that zone, and
  analytics days are counted in it, so the whole team sees the same numbers.
- Job alerts go out at about 8 am in each person's own zone. The command runs every hour and picks
  the people whose morning it is, which stays right across daylight saving changes.
- Only time zones the server can actually open are accepted, and old zone names that browsers still
  send are mapped to their current names.

## Consequences

- Every new date or time column needs a choice: moment or calendar date.
- The staff activity chart counts days in UTC and says so on the chart.
