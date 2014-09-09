# Simple WikiForum for MediaWiki

## Status
Not tested with anything since 2008 (when I developed this for Bernard Hulsman
of Wikiation.nl), and not the cleanest code. But you might be able to pluck
interesting bits from it here and there. Maybe it's even possible to make it
work on current MW, who knows. You have been warned ;)

## Description
What it does it basically recognize signatures in wikitext using a regexp.
Each post in a page is then identified by the hash of its heading text (and
a counter for duplicate heading) and the hash of the post itself (again with
counter for duplicates). The reply link opens a simple entry field similar
to the 'new thread' one and on submission splices in that post with the
right indentation level & automatic signature.

The nice thing about this scheme is that it can be switched on or off at
will, and simply works on existing talk pages. The bad thing is that it's
based on wikitext. These days I'd definitely base it on HTML.

## Copyright & License
- GPLv2
- (c) 2007-2008 Wikiation.nl
