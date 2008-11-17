PHP5 Feed parser and normaliser
===============================

A feed parser to normalise typical feed data into Atom-like constructs.
This parser supports / will support:

* RSS 2.0 plus it's descendant 0.9x versions
* RSS 1.0 (RDF Site Summary)
* Atom (not yet)

It also supports namespaces, through extensions.
So far, it supports the following namespaces:

* iTunes
* Yahoo!'s Media RSS
* Feedburner
* Dublin Core metadata (partial)
* Syndication (from the OCS format)

The parser is just a layer on-top of the built-in SAX XML parser that keeps
track of the element stack in a generic way. It offloads all of the real
data work to PHP classes - one class per namespace. That means the parser
itself can be extended to support any Feed based namespace.

The end result is a feed data structure where the three basic information
elements in a feed -- title, link and summary -- are normalised to Atom-like
equivalents, but all of the data from the XML feed is available within the
data structure.

Also, when complete, the feed parser will allow feed and entry normalisation,
so the actual contents of the feed structure can be tailored or simplified
using Normalisation PHP classes.

In addition to this, domain-specific processing can be added so that sites
that do things differently and oddly can be brought into line, and the
end result is a feed data structure that's normalised to something more
sane and easier for an application to consume.


List of issues
--------------

* atom:links structure needs to be normalised to remove duplicates
  (such as duplicates of enclosures thanks to multiple namespaces)
* where rss20:author is an email with a bracketed name, create a regex
  that will split the two up and populate both the atom:author name
  and email.
* how to deal with attributes that are namespaced (like flickr:profile
  on rss20:author
* how to deal with dublin core (or other external elements) when they
  are children of something other than feed and entry. And without
  dublin core having to know about other possible levels. Can we create
  a 'current parent' object that this info can be attached to?
  Something like $this->currentParent->{$elData->nsName} = $elData->text
* how to capture RSS2.0's isPermaLink attribute on the rss20:guid field.
* media:content - when video links don't supply a valid mime-type, but
  return an attribute with a value of 'video' or 'audio', how to map
  that adequately into a valid atom:link type.
* A flag/option that normalises times into a user-specified timezone.
  At the moment, any conversions are made to GMT, which is a decent
  start, I guess.
* How to handle invalid RFC-822 formats - do I write a custom method
  that gets called when we get 1 Jan 1970?
* dc:creator on the Flickr RDF feed returns a bracketed website URL and
  an unbracketed name. That can be translated into name and url of
  atom:author.
* When the rss20:author contains two people, whether to convert that into
  two atom:authors, and whether to remove the 'By' prefix on some
  rss20:author fields.
  
  


