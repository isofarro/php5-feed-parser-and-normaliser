<?php


/**
 * An abstract class that supports parsing and processing namespaced data
 * in RSS or Atom feeds. To use this class create a new class named along
 * the lines of {Prefix}NamespaceHandler that extends 
 * AbstractFeedNamespaceHandler, where {Prefix} is the internal shortname
 * of the namespace, with the first letter uppercased. For example: 
 * The Dublin Core namespace is named internally as 'dc', so the class to
 * handle this namespaced data must be called DcNamespaceHandler.
 *
 * This class needs to implement two methods:
 * * startElement($elData)
 * * endElement($elData)
 * 
 * where $elData is a data structure representing the current namespaced
 * element.
 *
 **/
abstract class AbstractFeedNamespaceHandler {
	public    $prefix='XXX';
	protected $parser;

	protected $feed;
	protected $entry;
	protected $isFeed;
	protected $isEntry;
	
	public function __construct($parser) {
		$this->parser = $parser;
	}
	
	abstract public function startElement($elData);
	abstract public function endElement($elData);
	
	public function startElementCallback($elData) {
		$this->_refDataFromParser();
		$this->startElement($elData);
		$this->_refDataToParser();
	}

	public function endElementCallback($elData) {
		$this->_refDataFromParser();
		$this->endElement($elData);
		$this->_refDataToParser();
	}
	
	private function _refDataFromParser() {
		$this->isFeed  = $this->parser->isFeed;
		$this->isEntry = $this->parser->isEntry;
		$this->entry   = $this->parser->entry;
		$this->feed    = $this->parser->feed;
	}
	
	private function _refDataToParser() {
		$this->parser->isFeed  = $this->isFeed;
		$this->parser->isEntry = $this->isEntry;
		$this->parser->entry   = $this->entry;
		$this->parser->feed    = $this->feed;	
	}
}

/**
 * An implementation of a FeedNamespaceHandler for RSS2.0 elements.
 * Handles all known RSS 2.0-like elements
 * This is also meant to support RSS versions 0.9X
 * Does some conversion to Atom where possible, and leaves 
 * the non-transferable elements namespaced.
 * 
 * http://www.rssboard.org/rss-specification
 **/
class Rss20NamespaceHandler extends AbstractFeedNamespaceHandler {
	public $prefix = 'rss20';
	
	// RSS 2.0 specific container flags
	private $isImage = false;
	private $image;
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function startElement($elData) {
		switch($elData->elName) {
			case 'rss':
				$this->isFeed = true;
				break;
				
			case 'item':
				$this->isEntry = true;
				break;

			case 'image':
				$this->isImage = true;
				$this->image = (object) NULL;
				break;
				
			// Quietly do nothing.
			case 'author':
			case 'category':
			case 'channel':
			case 'cloud':
			case 'comments':
			case 'copyright':
			case 'description':
			case 'docs':
			case 'enclosure':
			case 'generator':
			case 'guid':
			case 'height':
			case 'language':
			case 'lastBuildDate':
			case 'link':
			case 'managingEditor':
			case 'pubDate':
			case 'source':
			case 'rating':
			case 'title':
			case 'ttl':
			case 'url':
			case 'webMaster':
			case 'width':
				break;
				
			default:
				echo "START RSS2.0: $elData->elName not handled.\n";
				break;
		}
	}
	
	/**
	 * Callback handler for the FeedParser's endElement callback
	 **/
	public function endElement($elData) {
		switch($elData->elName) {
			case 'rss':
				$this->isFeed = false;
				break;
				
			case 'item':
				$this->isEntry = false;
				break;
				
			case 'image':
				$this->isImage = false;
				$this->feed->{$elData->nsName} = $this->image;
				break;
				
			case 'height':
				if ($this->isImage) {
					$this->image->height = $elData->text;
				}
				break;

			case 'width':
				if ($this->isImage) {
					$this->image->width = $elData->text;
				}
				break;

			case 'title': // translate to atom:title
				if ($this->isEntry) {
					if (empty($this->entry->title)) {
						$this->entry->title = $elData->text;
					}
				} elseif($this->isImage) {
					$this->image->title = $elData->text;
				} elseif($this->isFeed) {
					if (empty($this->feed->title)) {
						$this->feed->title  = $elData->text;
					}
				}
				break;
				
			case 'description': // translate to atom:summary
				if ($this->isEntry) {
					$this->entry->summary = $elData->text;
				} elseif($this->isFeed) {
					// translate to atom:subtitle on the feed level.
					$this->feed->subtitle  = $elData->text;
				}
				break;
				
			case 'pubDate': // translate to atom:published
				if ($this->isEntry) {
					$this->entry->published = date('c', strtotime($elData->text));
					$this->entry->{$elData->nsName} = $elData->text;
				} elseif ($this->isFeed) {
					// pubDate has a special meaning on the feedlevel.
					$this->feed->{$elData->nsName} = $elData->text;
				}
				break;
				
			case 'lastBuildDate': // translate to atom:updated
				if ($this->isEntry) {
					// Do nothing
				} elseif($this->isFeed) {
					$this->feed->updated = date('c', strtotime($elData->text));
					$this->feed->{$elData->nsName} = $elData->text;
				}
				break;
				
			case 'category': // translate to atom:category
				$category = (object) NULL;
				$category->term = $elData->text;
				if (!empty($elData->attr['domain'])) {
					$category->scheme = $elData->attr['domain'];
				}

				if ($this->isEntry) {
					if (empty($this->entry->categories)) {
						$this->entry->categories = array();
					}
					array_push($this->entry->categories, $category);
				} elseif ($this->isFeed) {
					if (empty($this->feed->categories)) {
						$this->feed->categories = array();
					}
					array_push($this->feed->categories, $category);
				}
				break;
				
			case 'link': // translate to atom:link
				$link = (object) NULL;
				$link->rel  = 'alternate';
				$link->type = 'text/html';
				$link->href = $elData->text;

				if ($this->isEntry) {
					if (empty($this->entry->links)) {
						$this->entry->links = array();
					}
					array_push($this->entry->links, $link);
				} elseif($this->isImage) {
					// No atom:link here.
					$this->image->link = $elData->text;
				} elseif($this->isFeed) {
					if (empty($this->feed->links)) {
						$this->feed->links = array();
					}
					array_push($this->feed->links, $link);
				}
				break;

			case 'enclosure': // Translate to atom:link
				if ($this->isEntry) {
					$link = (object) NULL;
					$link->href   = $elData->attr['url'];
					$link->rel    = 'enclosure';
					if (!empty($link->length)) {
						$link->length = $elData->attr['length'];
					}
					$link->type   = $elData->attr['type'];
					if (empty($this->entry->links)) {
						$this->entry->links = array();
					}
					array_push($this->entry->links, $link);
				}
				break;

			case 'comments': // Translate to atom:link
				if ($this->isEntry) {
					$link = (object) NULL;
					$link->href   = $elData->attr['url'];
					$link->rel    = 'comments';
					$link->type   = 'text/html';
					if (empty($this->entry->links)) {
						$this->entry->links = array();
					}
					array_push($this->entry->links, $link);
				}
				break;
				
			case 'url': 
				if ($this->isImage) {
					$this->image->url = $elData->text;
				}
				break;
				
			case 'copyright': // translate to atom:rights
				if ($this->isEntry) {
					// Do nothing
				} elseif ($this->isFeed) {
					$this->feed->rights = $elData->text;
				}
				break;
				
			case 'generator': // translate to atom:generator
				if ($this->isEntry) {
					// Do nothing
				} elseif ($this->isFeed) {
					$this->feed->generator = $elData->text;
				}
				break;
				
			case 'author': // RSS2.0 author to atom:author/email
			case 'managingEditor':
				$author = (object) NULL;
				
				// Check author really contains an email address
				if (strpos($elData->text, '@') > 0) {
					$author->name  = $elData->text; // for the time being
					$author->email = $elData->text;
				} else {
					$author->name = $elData->text;
				}
				
				if ($this->isEntry) {
					if (empty($this->entry->authors)) {
						$this->entry->authors = array();
					}
					array_push($this->entry->authors, $author);
				} elseif($this->isFeed) {
					if (empty($this->entry->authors)) {
						$this->entry->authors = array();
					}
					array_push($this->entry->authors, $author);
				}
				break;
				
			case 'source':
				$source = (object) NULL;
				$source->url = $elData->attr['url'];
				$source->source = $elData->text;
				if ($this->isEntry) {
					$this->entry->{$elData->nsName} = $source;
				}
				break;
			
			// attribute-based RSS2.0 elements that remain namespaced
			case 'cloud':
				if ($this->isEntry) {
					// Do nothing.
				} elseif($this->isFeed) {
					$this->feed->{$elData->nsName} = $elData->attr;
				}
				
			// RSS2.0 GUID is a unique identifier. 
			case 'guid':
				if ($this->isEntry) {
					$this->entry->{$elData->nsName} = $elData->text;
					$this->entry->id = $elData->text;
				} elseif ($this->isFeed) {
					$this->feed->{$elData->nsName} = $elData->text;				
					$this->feed->id = $elData->text;				
				}
			// RSS2.0 elements that remain namespaced
			case 'docs':		
			case 'language':
			case 'rating':
			case 'ttl':
			case 'webMaster':
				if ($this->isEntry) {
					$this->entry->{$elData->nsName} = $elData->text;
				} elseif ($this->isFeed) {
					$this->feed->{$elData->nsName} = $elData->text;				
				}
				break;
				
			// Quietly do nothing.
			case 'channel':
				break;
				
			default:
				echo "END RSS2.0:   $elData->elName not handled.\n";
				break;
		}
	}
}

/**
 * An implementation of FeedNamespaceHandler for the Atom namespace/feeds
 *
 * http://www.atompub.org/rfc4287.html
 **/
class AtomNamespaceHandler extends AbstractFeedNamespaceHandler {
	public $prefix = 'atom';
	
	private $isAuthor      = false;
	private $isContributor = false;
	private $isSource      = false;
	private $author;
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function startElement($elData) {
		switch($elData->elName) {
			case 'feed':
				$this->isFeed = true;
				break;
				
			case 'entry':
				$this->isEntry = true;
				break;
				
			case 'author':
				$this->author = (object) NULL;
				$this->isAuthor = true;
				break;
				
			case 'contributor':
				$this->author = (object) NULL;
				$this->isContributor = true;
				break;
				
			case 'source':
				$this->isSource = true;
				break;

			case 'category':
			case 'content':
			case 'email':
			case 'generator':
			case 'icon':
			case 'id':
			case 'link':
			case 'logo':
			case 'name':
			case 'published':
			case 'rights':
			case 'subtitle':
			case 'summary':
			case 'title':
			case 'updated':
			case 'uri':
				break;

			default:
				echo "START atom: $elData->elName not handled.\n";
				break;
		}
	}
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function endElement($elData) {
		switch($elData->elName) {
			case 'feed':
				$this->isFeed = false;
				break;
				
			case 'entry':
				$this->isEntry = false;
				break;
				
			case 'source':
				$this->isSource = false;
				break;
				
			case 'author':
				if ($this->isSource) {
					// Do nothing
				} elseif ($this->isEntry) {
					if (empty($this->entry->authors)) {
						$this->entry->authors = array();
					}
					array_push($this->entry->authors, $this->author);
				} elseif ($this->isFeed) {
					if (empty($this->feed->authors)) {
						$this->feed->authors = array();
					}
					array_push($this->feed->authors, $this->author);
				}
				$this->isAuthor = false;
				break;
				
			case 'contributor':
				if ($this->isSource) {
					// Do nothing
				} elseif ($this->isEntry) {
					if (empty($this->entry->contributors)) {
						$this->entry->contributors = array();
					}
					array_push($this->entry->contributors, $this->author);
				} elseif ($this->isFeed) {
					if (empty($this->feed->contributors)) {
						$this->feed->contributors = array();
					}
					array_push($this->feed->contributors, $this->author);
				}
				$this->isContributor = false;
				break;
				
			// contributor and author elements
			case 'name':
			case 'email':
			case 'uri':
				if ($this->isSource) {
					// Do nothing
				} elseif ($this->isAuthor || $this->isContributor) {
					$this->author->{$elData->elName} = $elData->text;
				}
				break;
				
			case 'content':
				$content = (object) NULL;

				// what type of content do we have?
				if (!empty($elData->attr['src'])) {
					// This can only be atom:content
					if ($elData->elName=='content') {
						$content->src = $elData->attr['src'];
					}

					if (!empty($elData->attr['type'])) {
						$content->type = $elData->attr['type'];
					}
				} elseif(!empty($elData->text)) {
					$content->text = $elData->text;
					// Make sure the output is HTML-ready
					if (!empty($elData->attr['type'])) {
						if ($elData->attr['type'] == 'html') {
							// Do nothing new
						} elseif ($elData->attr['type'] == 'text') {
							// Entity escape => transform to HTML-ready
							$content->text = htmlentities($content->text);
						} elseif ($elData->attr['type'] == 'xhtml') {
							echo "WARN: Contains real XHTML content.\n";
						} else {
							// So we probably have a mime type
							$content->type = $elData->attr['type'];
						}
					} else {
						// no type attribute -- so it's plain text
						$content->text = htmlentities($content->text);
					}
				}
				
				if ($this->isSource) {
					// Do nothing
				} elseif ($this->isEntry) {
					$this->entry->content = $content;
				}
				break;
				
			case 'rights':
			case 'subtitle':
			case 'summary':
			case 'title':
				$content = $elData->text;

				if (!empty($elData->attr['type'])) {
					if ($elData->attr['type'] == 'html') {
						// Do nothing new
					} elseif ($elData->attr['type'] == 'text') {
						// Entity escape => transform to HTML-ready
						$content = htmlentities($content);
					} elseif ($elData->attr['type'] == 'xhtml') {
						echo "WARN: Contains real XHTML content.\n";
					}
				} else {
					// no type attribute -- so it's plain text
					$content = htmlentities($content);
				}

				if ($this->isSource) {
					// Do nothing
				} elseif ($this->isEntry) {
					$this->entry->{$elData->elName} = $content;
				} elseif ($this->isFeed) {
					$this->feed->{$elData->elName} = $content;
				}
				break;

			case 'link':
				$link = (object) $elData->attr;
				if ($this->isSource) {
					// Do nothing
				} elseif ($this->isEntry) {
					if (empty($this->entry->links)) {
						$this->entry->links = array();
					}
					array_push($this->entry->links, $link);
				} elseif($this->isFeed) {
					if (empty($this->feed->links)) {
						$this->feed->links = array();
					}
					array_push($this->feed->links, $link);
				}
				break;
				
			case 'category':
				$category = (object) NULL;
				$category->term = $elData->attr['term'];
				if (!empty($category->attr['scheme'])) {
					$category->scheme = $category->attr['scheme'];
				}
				if (!empty($category->attr['label'])) {
					$category->label = $category->attr['label'];
				}
				
				if ($this->isSource) {
					// Do nothing
				} elseif ($this->isEntry) {
					if (empty($this->entry->categories)) {
						$this->entry->categories = array();
					}
					array_push($this->entry->categories, $category);
				}
				break;
				
			case 'generator':
				$generator = (object) NULL;
				$generator->generator = $elData->text;
				if (!empty($elData->attr['uri'])) {
					$generator->uri = $elData->attr['uri'];
				}
				if (!empty($elData->attr['version'])) {
					$generator->uri = $elData->attr['version'];
				}

				if ($this->isSource) {
					// Do nothing
				} elseif ($this->isEntry) {
					// Do nothing
				} elseif ($this->isFeed) {
					$this->feed->generator = $generator;
				}
				break;

			// Copy-across type data				
			case 'icon':
			case 'id':
			case 'logo':
			case 'published':
			case 'updated':
				if ($this->isSource) {
					// Do nothing
				} elseif ($this->isEntry) {
					$this->entry->{$elData->elName} = $elData->text;
				} elseif ($this->isFeed) {
					$this->feed->{$elData->elName} = $elData->text;
				}
				break;
				
			default:
				echo "END atom:   $elData->elName not handled.\n";
				break;
		}
	}
}


/**
 * An implementation of FeedNamespaceHandler for the RDF namespace
 * Handles RDF namespaced elements in RSS
 *
 * http://www.w3.org/TR/rdf-syntax-grammar/
 * No, I didn't implement that, just enough to parse an RSS1.0 feed.
 * Which basically means ignoring everything in this namespace :-)
 **/
class RdfNamespaceHandler extends AbstractFeedNamespaceHandler {
	public $prefix = 'rdf';
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function startElement($elData) {
		switch($elData->elName) {
			case 'li':
			case 'RDF':
			case 'Seq':
				break;

			default:
				echo "START rdf: $elData->elName not handled.\n";
				break;
		}
	}
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function endElement($elData) {
		switch($elData->elName) {
			case 'li':
			case 'RDF':
			case 'Seq':
				break;
				
			default:
				echo "END rdf:   $elData->elName not handled.\n";
				break;
		}
	}
}


/**
 * An implementation of FeedNamespaceHandler for the RSS 1.0 namespace
 * Handles RSS 1.0 namespaced elements in RSS
 *
 * http://web.resource.org/rss/1.0/spec
 **/
class Rss10NamespaceHandler extends AbstractFeedNamespaceHandler {
	public $prefix = 'rss10';
	
	private $rdfNamespaceUri = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

	// Dealing with an RSS 1.0 image	
	private $isImage = false;
	private $image;
	
	// Dealing with an RSS 1.0 textinput
	private $isTextinput = false;
	private $textinput;
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function startElement($elData) {
		switch($elData->elName) {
			case 'channel': // @rdf:about is the feed atom:id
				$rdfAbout = $this->getRdfAttr('about', $elData);
				if (!empty($rdfAbout)) {
					$this->feed->id = $rdfAbout;
				}
				$this->isFeed = true;
				break;
				
			case 'item': // @rdf:about is the entry atom:id
				$rdfAbout = $this->getRdfAttr('about', $elData);
				if (!empty($rdfAbout)) {
					$this->entry->id = $rdfAbout;
				}
				$this->isEntry = true;
				break;

			case 'image':
				if ($this->isFeed || $this->isEntry) {
					// Do nothing
				} else {
					$this->image = (object) NULL;
					$this->isImage = true;
				}
				break;

			case 'textinput':
				if ($this->isFeed || $this->isEntry) {
					// Do nothing
				} else {
					$this->textinput   = (object) NULL;
					$this->isTextinput = true;
				}
				break;

			case 'description':
			case 'items':
			case 'link':
			case 'title':
			case 'url':
				break;
				
			default:
				echo "START rss10: $elData->elName not handled.\n";
				break;
		}
	}
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function endElement($elData) {
		switch($elData->elName) {
			case 'channel':
				$this->isFeed = false;
				break;

			case 'item':
				$this->isEntry = false;
				break;
				
			case 'title': // translate to atom:title
				if ($this->isImage) {
					$this->image->{$elData->nsName} = $elData->text;
				} elseif ($this->isTextinput) {
					$this->textinput->{$elData->nsName} = $elData->text;
				} elseif($this->isEntry) {
					$this->entry->title = $elData->text;
				} elseif ($this->isFeed) {
					$this->feed->title = $elData->text;
				}
				break;
				
			case 'link': // translate to atom:link
				$link = (object) NULL;
				$link->href = $elData->text;
				$link->rel  = 'alternate';
				$link->type = 'text/html';
				if ($this->isImage) {
					$this->image->{$elData->nsName} = $elData->text;
				} elseif ($this->isTextinput) {
					$this->textinput->{$elData->nsName} = $elData->text;
				} elseif ($this->isEntry) {
					if (empty($this->entry->links)) {
						$this->entry->links = array();
					}
					array_push($this->entry->links, $link);
				} elseif ($this->isFeed) {
					if (empty($this->feed->links)) {
						$this->feed->links = array();
					}
					array_push($this->feed->links, $link);
				}
				break;
				
			case 'description': // translate to atom:summary
				if ($this->isEntry) {
					$this->entry->summary = $elData->text;
				} elseif ($this->isTextinput) {
					$this->textinput->{$elData->nsName} = $elData->text;
				} elseif ($this->isFeed) {
					// translate to atom:subtitle on the feed level.
					$this->feed->subtitle = $elData->text;
				}
				break;
				
			case 'image':
				if ($this->isImage) {
					$this->feed->{$elData->nsName} = $this->image;
					$this->isImage = false;
				}
				break;
				
			case 'url':
				if ($this->isImage) {
					$this->image->{$elData->nsName} = $elData->text;				
				}
				break;
				
			case 'textinput':
				if ($this->isTextinput) {
					$this->feed->{$elData->nsName} = $this->textinput;
					$this->isTextinput = false;
				}
				break;
				
			case 'name':
				if ($this->isTextinput) {
					$this->textinput->{$elData->nsName} = $elData->text;
				}
				break;

			case 'items':
				break;
				
			default:
				echo "END rss10:   $elData->elName not handled.\n";
				break;
		}
	}
	
	public function getRdfAttr($attr, $elData) {
		$attrName = $this->rdfNamespaceUri . ':' . $attr;
		if (!empty($elData->attr[$attrName])) {
			return $elData->attr[$attrName];
		}
		return NULL;
	}
}



/**
 * An implementation of FeedNamespaceHandler for the Feedburner namespace
 * Handles feedBurner namespaced elements in RSS
 *
 * http://code.google.com/apis/feedburner/feedburner_namespace_reference.html
 **/
class FeedburnerNamespaceHandler extends AbstractFeedNamespaceHandler {
	public $prefix = 'feedburner';
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function startElement($elData) {
		switch($elData->elName) {
			// Ignore
			case 'browserFriendly':
			case 'feedFlare':
			case 'origEnclosureLink':
			case 'origLink':
				break;

			default:
				echo "START feedburner: $elData->elName not handled.\n";
				break;
		}
	}
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function endElement($elData) {
		switch($elData->elName) {
			case 'origLink':
			case 'origEnclosureLink':
				if ($this->isEntry) {
					$this->entry->{$elData->nsName} = $elData->text;
				} elseif($this->isFeed) {
					// Do nothing
				}
				break;
				
			case 'browserFriendly':
				if ($this->isEntry) {
					// Do nothing
				} elseif($this->isFeed) {
					$this->feed->{$elData->nsName} = $elData->text;
				}
				break;
				
			case 'feedFlare':
				$flare = (object) NULL;
				$flare->href = $elData->attr['href'];
				$flare->src  = $elData->attr['src'];
				$flare->text = $elData->text;

				if ($this->isEntry) {
					// Do nothing
				} elseif($this->isFeed) {
					if (empty($this->feed->{'feedburner-feedFlares'})) {
						$this->feed->{'feedburner-feedFlares'} = array();
					}
					array_push($this->feed->{'feedburner-feedFlares'}, $flare);
				}
				break;
				
			default:
				echo "END feedburner:   $elData->elName not handled.\n";
				break;
		}
	}
}


/**
 * An implementation of FeedNamespaceHandler for the Dublin Core namespace
 * Handles dublin core namespaced elements in RSS
 *
 * http://dublincore.org/documents/dces/
 * http://web.resource.org/rss/1.0/modules/dc/
 **/
class DcNamespaceHandler extends AbstractFeedNamespaceHandler {
	public $prefix = 'dc';
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function startElement($elData) {
		switch($elData->elName) {
			case 'creator':
			case 'date':
			case 'description':
			case 'language':
			case 'publisher':
			case 'rights':			
			case 'subject':
			case 'title':
			case 'type':
				break;

			default:
				//echo "START dc: $elData->elName not handled.\n";
				break;
		}
	}
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function endElement($elData) {
		switch($elData->elName) {
			case 'creator': // translate to atom:author/name
				$author = (object) NULL;
				$author->name = $elData->text;
				if ($this->isEntry) {
					if (empty($this->entry->authors)) {
						$this->entry->authors = array();
					}
					array_push($this->entry->authors, $author);
				} elseif ($this->isFeed) {
					if (empty($this->feed->authors)) {
						$this->feed->authors = array();
					}
					array_push($this->feed->authors, $author);
				}
				break;
				
			case 'date': // translate to atom:published
				if ($this->isEntry) {
					if (empty($this->entry->published)) {
						$this->entry->published = $elData->text;
					}
				} elseif ($this->isFeed) {
					if (empty($this->feed->updated)) {
						$this->feed->updated = $elData->text;
					}
				}
				break;

			case 'description':
			case 'publisher':
			case 'language':		
			case 'subject':
			case 'rights':
			case 'title':
			case 'type':
				if ($this->isEntry) {
					$this->entry->{$elData->nsName} = $elData->text;
				} elseif ($this->isFeed) {
					$this->feed->{$elData->nsName} = $elData->text;
				}
				break;
				
			default:
				//echo "END dc:   $elData->elName not handled.\n";
				break;
		}
	}
}


/**
 * An implementation of FeedNamespaceHandler for the Syndication namespace
 * Handles syndication namespaced elements in RSS
 *
 * http://web.resource.org/rss/1.0/modules/syndication/
 **/
class SynNamespaceHandler extends AbstractFeedNamespaceHandler {
	public $prefix = 'syn';
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function startElement($elData) {
		switch($elData->elName) {
			case 'updateBase':
			case 'updateFrequency':
			case 'updatePeriod':
				break;
				
			default:
				echo "START syn: $elData->elName not handled.\n";
				break;
		}
	}
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function endElement($elData) {
		switch($elData->elName) {
			case 'updateBase':
			case 'updateFrequency':
			case 'updatePeriod':
				if ($this->isFeed) {
					$this->feed->{$elData->nsName} = $elData->text;
				}
				break;
				
			default:
				echo "END syn:   $elData->elName not handled.\n";
				break;
		}
	}
}



/**
 * An implementation of FeedNamespaceHandler for the Yahoo Media namespace
 * Handles Yahoo media namespaced elements in RSS
 *
 * http://search.yahoo.com/mrss
 **/
class MediaNamespaceHandler extends AbstractFeedNamespaceHandler {
	public $prefix = 'dc';
	
	// Media containers/collections
	private $isGroup   = false;
	private $isContent = false;
	private $group;
	private $content;
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function startElement($elData) {
		switch($elData->elName) {
			case 'group':
				$this->isGroup = true;
				$this->group   = array();
				break;
				
			case 'content':
				$this->isContent = true;
				// Populate with all the attributes
				$this->content = (object) $elData->attr;
				break;
				
			case 'category':
			case 'copyright':
			case 'credit':
			case 'description':
			case 'hash':
			case 'keywords':
			case 'player':
			case 'rating':
			case 'restriction':
			case 'text':
			case 'title':
			case 'thumbnail':
				break;
				
			// Batshit crazy New York Times 'attributes-not-good-enough'
			case 'url':
			case 'medium':
			case 'height':
			case 'width':
				break;
				
			default:
				echo "START media: $elData->elName not handled.\n";
				break;
		}
	}
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function endElement($elData) {
		switch($elData->elName) {
			case 'content': // translate into atom:link too
				// Create atom:link structure
				$link = (object) NULL;
				$link->rel = 'enclosure';
				
				// atom:link @href
				if (!empty($this->content->url)) {
					$link->href = $this->content->url;
				} elseif (!empty($this->content->player->url)) {
					$link->href = $this->content->player->url;
				}
				
				// atom:link @type
				if (!empty($elData->attr['type'])) {
					$link->type = $elData->attr['type'];
				} elseif (!empty($elData->attr['medium'])) {
					$link->type = $elData->attr['medium'] . '/*';
				}
				
				// atom:link @length
				if (!empty($elData->attr['filesize'])) {
					$link->length = $elData->attr['filesize'];
				}
				
				if ($this->isGroup) {
					if (empty($this->content->url)) {
						// TODO: this needs to be more robust.
						// Need to be able to iterate through the links array
						// to find the right enclosure link.
						echo "WARN: Guessing the media:content href.\n";
						if (!empty($this->entry->links[0]->href)) {
							$this->content->url = $this->entry->links[0]->href;
						}
					}
					array_push($this->group, $this->content);
				} elseif ($this->isEntry) {
					// Add enclosure to atom:links structure
					if (!empty($link->href)) {
						if (empty($this->entry->links)) {
							$this->entry->links = array();
						}
						array_push($this->entry->links, $link);
					}
					
					// Add it with the media:contents structure
					if (!empty($this->content->url)) {
						if (empty($this->entry->{'media-contents'})) {
							$this->entry->{'media-contents'} = array();
						}
						array_push($this->entry->{'media-contents'}, $this->content);
					}
				}
				$this->isContent = false;
				break;
				
			case 'group':
				if ($this->isEntry) {
					$this->entry->{$elData->nsName} = $this->group;
				}
				$this->isGroup = false;
				break;
				
			case 'thumbnail':
				$thumbnail = (object) $elData->attr;
				if ($this->isContent) {
					$this->content->thumbnail = $thumbnail;
				} elseif ($this->isGroup) {
					$this->group->thumbnail = $thumbnail;
				} elseif ($this->isEntry) {
					$this->entry->{$elData->nsName} = $thumbnail;
				} elseif ($this->isFeed) {
					$this->feed->{$elData->nsName} = $thumbnail;
				}
				break;
				
			case 'player':
				$player = (object) $elData->attr;
				if ($this->isContent) {
					$this->content->player = $player;
				} elseif ($this->isGroup) {
					$this->group->player = $player;
				} elseif ($this->isEntry) {
					$this->entry->player = $player;
				} elseif ($this->isFeed) {
					$this->feed->player = $player;
				}
				break;
				
			case 'text':
				$fieldName = 'media-texts';
				$text = (object) $elData->attr;
				$text->text = $elData->text;
				
				$this->addToFieldList($fieldName, $text);
				break;
				
			case 'restriction':
				$fieldName = 'media-restrictions';
				$restriction = (object) $elData->attr;
				$restriction->countries = explode(' ', $elData->text);
				
				$this->addToFieldList($fieldName, $restriction);
				break;
				
			case 'copyright':
				$copyright = (object) NULL;
				$copyright->text = $elData->text;
				if (!empty($elData->attr['url'])) {
					$copyright->url = $elData->attr['url'];
				}				
				
				if ($this->isContent) {
					$this->content->{$elData->nsName} = $copyright;
				} elseif ($this->isGroup) {
					$this->group->{$elData->nsName} = $copyright;
				} elseif ($this->isEntry) {
					$this->entry->{$elData->nsName} = $copyright;
				} elseif ($this->isFeed) {
					$this->feed->{$elData->nsName} = $copyright;
				}
				break;
				
			case 'credit':
				$fieldName = 'media-credits';
				
				// Create a media:credit structure
				$credit = (object) NULL;
				$credit->credit = $elData->text;
				if (!empty($elData->attr['role'])) {
					$credit->role = $elData->attr['role'];
				}
				if (!empty($elData->attr['scheme'])) {
					$credit->scheme = $elData->attr['scheme'];
				}

				$this->addToFieldList($fieldName, $credit);
				break;

			case 'category':
				$fieldName = 'media-categories';
				$category = (object) NULL;
				if(!empty($elData->attr['scheme'])) {
					$category->scheme = $elData->attr['scheme'];
				}
				if(!empty($elData->attr['label'])) {
					$category->label = $elData->attr['label'];
				}
				$category->term   = $elData->text;
				
				$this->addToFieldList($fieldName, $category);
				break;
				
			case 'hash':
				$fieldName = 'media-hashes';
				$hash = (object) NULL;
				if(!empty($elData->attr['algo'])) {
					$hash->algo = $elData->attr['algo'];
				} else {
					$hash->algo = 'md5';
				}
				$hash->text   = $elData->text;

				$this->addToFieldList($fieldName, $hash);
				break;
				
			case 'rating':
				$fieldName = 'media-ratings';
				$rating = (object) NULL;
				if (!empty($elData->attr['scheme'])) {
					$rating->scheme = $elData->attr['scheme'];
				} else {
					$rating->scheme = 'urn:simple';
				}
				$rating->term = $elData->text;
				
				$this->addToFieldList($fieldName, $rating);
				break;
				
			case 'title':
			case 'description':
				$text = (object) NULL;
				$text->{$elData->elName} = $elData->text;
				if (!empty($elData->attr['type'])) {
					$text->type = $elData->attr['type'];
				}
				
				if ($this->isContent) {
					$this->content->{$elData->nsName} = $text;
				} elseif($this->isGroup) {
					array_push($this->group, $text);
				} elseif($this->isEntry) {
					$this->entry->{$elData->nsName} = $text;
				} elseif($this->isFeed) {
					$this->feed->{$elData->nsName} = $text;
				}
				break;
				
			case 'keywords':
				if (!empty($elData->text)) {
					$list = preg_replace('/, /', ',', $elData->text);
					$keywords = explode(',', $list);
					if ($this->isContent) {
						$this->content->{$elData->nsName} = $keywords;
					} elseif($this->isGroup) {
						$this->group->{$elData->nsName} = $keywords;
					} elseif ($this->isEntry) {
						$this->entry->{$elData->nsName} = $keywords;
					} elseif ($this->isFeed) {
						$this->feed->{$elData->nsName} = $keywords;
					}
				}
				break;

			// Batshit crazy New York Times 'attributes-not-good-enough'
			case 'url':
			case 'medium':
			case 'height':
			case 'width':
				if($this->isContent) {
					$this->content->{$elData->elName} = $elData->text;
				}
				break;

			default:
				echo "END media:   $elData->elName not handled.\n";
				break;
		}
	}
	
	private function addToFieldList($fieldName, $data) {
		if ($this->isContent) {
			if (empty($this->content->{$fieldName})) {
				$this->content->{$fieldName} = array();
			}
			array_push($this->content->{$fieldName}, $data);
		} elseif($this->isGroup) {
			array_push($this->group, $data);
		} elseif ($this->isEntry) {
			if (empty($this->entry->{$fieldName})) {
				$this->entry->{$fieldName} = array();
			}
			array_push($this->entry->{$fieldName}, $data);
		} elseif($this->isFeed) {
			if (empty($this->feed->{$fieldName})) {
				$this->feed->{$fieldName} = array();
			}
			array_push($this->feed->{$fieldName}, $data);
		}
	}
	
}


/**
 * An implementation of FeedNamespaceHandler for the iTunes namespace
 * Handles iTunes namespaced elements in RSS
 *
 * http://lists.apple.com/archives/syndication-dev/2005/Nov/msg00002.html#_Toc526931674
 **/
class ItunesNamespaceHandler extends AbstractFeedNamespaceHandler {
	public $prefix = 'itunes';
	
	// itunes:category
	private $isCategory = false;
	private $isSubCategory = false;
	private $category;
	
	// itunes:owner
	private $isOwner = false;
	private $owner;
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function startElement($elData) {
		switch($elData->elName) {
			case 'owner':
				$this->isOwner = true;
				$this->owner = (object) NULL;
				break;
		
			case 'category':
				if ($this->isCategory) {
					if (!empty($elData->attr['text'])) {
						$this->category->subcategory = (object) NULL;
						$this->category->subcategory->text = $elData->attr['text'];
					}
					$this->isSubCategory = true;
				} elseif($this->isSubCategory) {
					echo "WARN: itunes:subcategory has a subcategory itself.\n";
				} else {
					// A new itunes:category node
					$this->category = (object) NULL;
					if (!empty($elData->attr['text'])) {
						$this->category->text = $elData->attr['text'];
					}
					$this->isCategory = true;
				}
				break;
		
			case 'author':
			case 'block':
			case 'duration':
			case 'email':
			case 'explicit':
			case 'image':
			case 'keywords':
			case 'name':
			case 'new-feed-url':
			case 'pubDate':
			case 'subtitle':
			case 'summary':
				break;

			default:
				echo "START itunes: $elData->elName not handled.\n";
				break;
		}
	}
	
	/**
	 * Callback handler for the FeedParser's startElement callback
	 **/
	public function endElement($elData) {
		switch($elData->elName) {
			case 'owner':
				if ($this->isEntry) {
					// Do nothing
					echo "WARN: itunes:owner at an entry level.\n";
				} elseif($this->isFeed) {
					$this->feed->{$elData->nsName} = $this->owner;
					$this->isOwner = false;
				}
				break;
			
			case 'email':
			case 'name':
				if ($this->isOwner) {
					$this->owner->{$elData->elName} = $elData->text;
				}
				break;
				
			case 'category':
				if ($this->isSubCategory) {
					// We've done all this work already.
					$this->isSubCategory = false;
				} elseif($this->isCategory) {
					//echo "INFO: end category: "; print_r($this->category);
					$emptyCategory = (
						empty($this->category->text) &&
						empty($this->category->subcategory)
					);
					if (!$emptyCategory) {
						$fieldName = 'itunes-categories';
						if ($this->isEntry) {
							echo "WARN: itunes:category at an entry level.\n";
						} elseif ($this->isFeed) {
							if (empty($this->feed->{$fieldName})) {
								$this->feed->{$fieldName} = array();
							}
							array_push($this->feed->{$fieldName}, $this->category);
						}
					} else {
						//echo "INFO: itunes:category empty.\n"; 
						//print_r($this->category);
					}
					$this->isCategory = false;
				}
				break;
				
			case 'keywords':
				if (!empty($elData->text)) {
					$list = preg_replace('/, /', ',', $elData->text);
					$keywords = explode(',', $list);
					if ($this->isEntry) {
						$this->entry->{$elData->nsName} = $keywords;
					} elseif ($this->isFeed) {
						$this->feed->{$elData->nsName} = $keywords;
					}
				}
				break;
		
			case 'author':
			case 'block':
			case 'duration':
			case 'explicit':
			case 'keywords':
			case 'new-feed-url':
			case 'pubDate':
			case 'subtitle':
			case 'summary':
				if ($this->isEntry) {
					if (!empty($elData->text)) {
						$this->entry->{$elData->nsName} = $elData->text;
					}
				} elseif ($this->isFeed) {
					if (!empty($elData->text)) {
						$this->feed->{$elData->nsName} = $elData->text;
					}
				}
				break;
			
			case 'image':
				if (!empty($elData->attr['href'])) {
					$image = (object) NULL;
					$image->href = $elData->attr['href'];
					
					if ($this->isEntry) {
						// Do nothing
					} elseif ($this->isFeed) {
						$this->feed->{$elData->nsName} = $image;
					}
				}
				break;
				
			default:
				echo "END itunes:   $elData->elName not handled.\n";
				break;
		}
	}
}



/**
 * Parses syndication feeds and returns a normalised data structure
 * based on Atom (RFC4287) constructs. The Parser supports data in
 * namespaces. Each namespace is handled by a separate *NamespaceHandler,
 * so the FeedParser is modular and extendable.
 * A feed can be normalised further by using site-specific FeedNormalisation
 * classes - so taking advantage of the peculiarities of more complex feeds
 * to return the best possible normalised output. The default normalisation
 * is to return as much data in Atom-like formats, and the rest as
 * namespace-like attributes and structures.
 *
 **/
class FeedParser {

	// Feed data structures
	public $isFeed;
	public $isEntry;
	public $feed;
	public $entry;

	// Internal variables for handling stacked elements
	private $elStack;
	private $curEl;
	
	// A normaliser class to simplify the feed data even more
	private $normaliser;
	
	// Default list of supported namespaces
	public $namespaces = array(
		'http://www.w3.org/2005/Atom'                  => 'atom',

		'http://purl.org/dc/elements/1.1/'             => 'dc',

		'http://rssnamespace.org/feedburner/ext/1.0'   => 'feedburner',
		'http://search.yahoo.com/mrss/'                => 'media',
		'http://www.itunes.com/dtds/podcast-1.0.dtd'   => 'itunes',

		'http://www.w3.org/1999/02/22-rdf-syntax-ns#'  => 'rdf',
		'http://purl.org/rss/1.0/'                     => 'rss10',
		'http://purl.org/rss/1.0/modules/syndication/' => 'syn',
		//'http://purl.org/rss/1.0/modules/content/'     => 'content',
		//'http://purl.org/rss/1.0/modules/taxonomy/'    => 'taxo',
		//'http://webns.net/mvcb/'                       => 'admin',
		//'http://www.w3.org/1999/xhtml'                 => 'xhtml',

		'' => 'rss20'
	);
	
	// References to instantiated namespace handler classes.
	private $nsHandlers = array();
	
	public function __construct(){
	}
	
	/**
	 * Adds a new namespace to be handled. The prefix is used to find
	 * a supporting NamespaceHandler class by uppercasing the first letter
	 * and prefixing it to NamespaceHandler. So the prefix 'rss20' means the
	 * parser looks for the class Rss20NamespaceHandler that extends
	 * FeedNamespaceHandler.
	 * 
	 * @param $uri The URI of the namespace
	 * @parma $prefix A short prefix for internal/extension use.
	 *
	 * Generates a warning if the namespace URI already exists.
	 */	 
	public function addNamespaceSupport($uri, $prefix) {
		if (strtolower($prefix)=='abstractfeed') {
			echo "WARN: Invalid prefix: $prefix\n";
		}
	
		if (empty($this->namespaces[$uri])) {
			$this->namespaces[$uri] = $prefix;
		} else {
			echo "WARN: $uri already added as $prefix\n";
		}
	}
	
	/**
	 * Parses a feed at the given URL and returns a feed object.
	 * Uses CURL.
	 *
	 * @param $url the URL of the feed to parse
	 * @returns $feed a normalised feed object
	 **/
	public function parse($url) {
		$xml = $this->getUrl($url);
		
		if ($xml && strlen($xml)>0) {
			return $this->parseXml($xml);
		} else {
			echo "ERROR: No feed returned from $url\n";
		}
	}
	
	private function initXmlData() {
		$this->elStack  = array();
		$this->curEl    = (object) NULL;
		
		// TODO: Need to find a way to make these flags extensible
		// So that dublin core elements get assigned to the right level.
		// For example dc:creator on an RSS1.0 image (AFP feed 024)
		$this->feed     = (object) NULL;
		$this->entry    = (object) NULL;
		$this->image    = (object) NULL;

		$this->isFeed   = false;
		$this->isEntry  = false;
		$this->isImage  = false;
	}

	/**
	 * Parses a supplied XML string and returns a normalised feed object.
	 *
	 * @param $xml a text string containing XML of a feed.
	 * @returns $feed a normalised feed object
	 **/
	public function parseXml($xml) {
		// Use the namespace aware parser
		$xmlParser = xml_parser_create_ns();
		$this->initXmlData();

		// case-sensitive element names
		xml_parser_set_option($xmlParser, XML_OPTION_CASE_FOLDING, 0);

		// Element handlers are part of this object
		xml_set_object($xmlParser, $this); 

		// Set the element handler functions
		xml_set_element_handler(
			$xmlParser, "startElement", "endElement"
		);
		xml_set_character_data_handler(
			$xmlParser, "characterData"
		);
		
		// Set the namespace handlers
		xml_set_start_namespace_decl_handler(
			$xmlParser, 'startNamespace'
		);

		if (!xml_parse($xmlParser, $xml)) {
			die(sprintf(
				"XML error: %s at line %d",
				xml_error_string(xml_get_error_code($xmlParser)),
				xml_get_current_line_number($xmlParser)
			));
		}
		xml_parser_free($xmlParser);
		return $this->feed;
	}
	
	private function startCurrentElement($tagName, $attr) {
		// Put the parent start element to the stack
		array_push($this->elStack, $this->curEl);
		
		// Normalise the tag name into something sensible
		list ($prefix, $elName) = $this->normaliseTagName($tagName);
		
		// Create the new start element -- this is the currently open element
		$this->curEl = (object) NULL;
		$this->curEl->tagName = $tagName;
		$this->curEl->attr    = $attr;

		$this->curEl->prefix  = $prefix;
		$this->curEl->elName  = $elName;
		$this->curEl->text    = '';
		$this->curEl->nsName  = 
			($prefix)?$prefix . '-' . $elName:$elName;
	}
	
	private function endCurrentElement() {
		// Get the parent start element -- this is the currently open element
		$this->curEl = array_pop($this->elStack);	
	}
	
	private function initNamespaceHandler($prefix) {
		//echo "INFO: Creating namespace handler for $prefix\n";
		$className = ucfirst($prefix) . 'NamespaceHandler';
		if (class_exists($className)) {
			$handler = new $className($this);
			if (is_a($handler, 'AbstractFeedNamespaceHandler')) {
				return $handler;
			}
		}
		return NULL;
	}
	
	private function getNamespaceHandler($prefix) {
		if (empty($this->nsHandlers[$prefix])) {
			$handler = $this->initNamespaceHandler($prefix);
			if (!empty($handler)) {
				$this->nsHandlers[$prefix] = $handler;
				return $handler;
			}
		} else {
			return $this->nsHandlers[$prefix];
		}
		return NULL;
	}
	
	/**
	 * Callback handler for the SAX parser's startElement event
	 **/
	public function startElement($parser, $tagName, $attr) {
		// Create a new Element data structure
		$this->startCurrentElement($tagName, $attr);

		if (!$this->isFeed) {
			// Identify the feed type
			// TODO: Allow this to be extensible
			switch($this->curEl->elName) {
				case 'rss':
				//case 'rss20-rss':
				//case 'XXX-rss':
					$this->isFeed = true;
					if (!empty($attr['version'])) {
						$this->feed->{'rss-version'} = $attr['version'];
						switch ($attr['version']) {
							case '2.0':
							case '0.92':
							case '0.91':
								// treat all these versions as RSS 2.0
								$this->feed->defaultNs = 'rss20';
								break;
								
							default:
								echo "ERROR: unsupported RSS variant",
									$attr['version'], "\n";
								$this->feed->defaultNs = 'rss' . 
									preg_replace('/\./', '', $attr['version']);
						}
					} else {
						$this->feed->defaultNs = 'rss20';
					}
					$this->curEl->prefix = $this->feed->defaultNs;
					break;
				default:
					break;
			}
		}
		
		$elHandler = $this->getNamespaceHandler($this->curEl->prefix);
		if (!empty($elHandler)) {
			$elHandler->startElementCallback($this->curEl);
		} else {
			//echo "START: ", $this->curEl->prefix, ":", $this->curEl->elName, "\n";
			//echo "WARN: No namespace handler for ", $this->curEl->prefix, "\n";
		}
	}

	/**
	 * Callback handler for the SAX parser's endElement event
	 **/
	public function endElement($parser, $tagName) {
		list($prefix,$elName) = $this->normaliseTagName($tagName);
		
		if (!empty($this->curEl->text)) {
			$this->curEl->text = trim($this->curEl->text);
		}

		$isEntryBefore = $this->isEntry;
		$elHandler = $this->getNamespaceHandler($this->curEl->prefix);
		if (!empty($elHandler)) {
			$elHandler->endElementCallback($this->curEl);
		} else {
			//echo "END:   ", $this->curEl->prefix, ":", $this->curEl->elName, "\n";
			//echo "WARN: No namespace handler for ", $this->curEl->prefix, "\n";
		}
		
		// If we've reached the end of an entry, then add
		// it to the feed entries and start a new entry.
		if ($isEntryBefore==true && $isEntryBefore != $this->isEntry) {
			//echo "INFO: End of current Entry.\n"; print_r($this->entry);
			if (empty($this->feed->entries)) {
				$this->feed->entries = array();
			}
			array_push($this->feed->entries, $this->entry);
			$this->entry = (object) NULL;
		}

		$this->endCurrentElement();
	}

	/**
	 * Callback handler for the SAX parser's startNamespace event
	 * This function tries to initialise a NamespaceHandler for
	 * each namespace declared.
	 **/
	public function startNamespace($parser, $prefix, $uri) {
		//echo "STARTNS: $prefix = $uri\n";

		// See if this namespace has been specified
		if (empty($this->namespaces[$uri])) {
			// Temporarily add it to our list of namespaces.
			// Who knows, maybe there's a handler class for it
			$this->addNamespaceSupport($uri, $prefix);
		}
		
		// Initialise the namespace handler now.
		if (!empty($this->namespaces[$uri])) {
			$handler = $this->getNamespaceHandler($this->namespaces[$uri]);
			if (empty($handler)) {
				echo "WARN: No namespace handler created for ",
					$this->namespaces[$uri], "\n";
			}
		}
	}

	/**
	 * Callback handler for the SAX parser's characterData event
	 **/
	public function characterData($parser, $data) {
		if(empty($this->curEl->text) && !trim($data)) { 
			return; 
		}
		//echo "[", trim($data), "]\n";
		$this->curEl->text .= $data;
	}
	
	private function normaliseTagName($tagName) {
		$segments = explode(':', $tagName);
		if (count($segments)>1) {
			$elName = array_pop($segments);
			$uri = implode(':', $segments);
			if (!empty($this->namespaces[$uri])) {
				$prefix = $this->namespaces[$uri];
			} else {
				echo "WARN: Namespace $uri not defined.\n";
				$prefix = 'XXX';
			}
			return array($prefix, $elName);
		} elseif (count($segments)==1) {
			if (!empty($this->feed->defaultNs)) {
				return array($this->feed->defaultNs, $tagName);
			}
			return array('XXX', $tagName);
		} else {
			echo "ERROR: Invalid tag name: $tagName\n";
			return array('XXX', $tagName);
		}
	}

	private function getUrl($url) {
		return $this->curlGet($url);
	}	
	
	private function curlGet($url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}
}

?>