# JacRed

Check that JacRed (the service that searches Russian trackers for releases) answers. We recommend the Melange server: it is selected by default and gets the fixes. You need another JacRed only if the Melange server doesn't answer. [← Contents](README.en.md)

1. On the settings page ([Settings page on a phone](phone-page.en.md)), open "JacRed" and press "Compare speed" at the bottom of the screen.

   <img src="../screenshots/jacred-compare.jpg" alt="JacRed screen: jacred.kompotik.com is selected, marked of Melange, the Compare speed button highlighted at the bottom" width="300">

2. Wait up to a minute. Each JacRed shows its response time and the number of releases for three movies. If the server marked "of Melange" has numbers, change nothing.

   <img src="../screenshots/jacred-result.jpg" alt="Each JacRed with time and number of releases, jacred.kompotik.com of Melange selected: 0.6 s · 252" width="300">

3. If the Melange server shows "no answer", choose the JacRed that answered fastest and press "Save". When the Melange server works again, switch back to it.

   <img src="../screenshots/phone-saved.jpg" alt="Green line at the top of the page: Saved. The Dune picks up the settings automatically." width="300">

Your own JacRed is covered in [AIOStreams and your own config](aiostreams.en.md).

## If it didn't work

- "no answer" for all servers: check the internet on the Dune and press "Compare speed" again.
- The JacRed server answers, but there are few releases: see [Known limitations](limitations.en.md).

Next: [Sources](profiles.en.md) · [Contents](README.en.md)
