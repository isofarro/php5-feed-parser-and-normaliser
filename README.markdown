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



