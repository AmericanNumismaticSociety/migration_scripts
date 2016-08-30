<?xml version="1.0" encoding="UTF-8"?>

<!-- Author: Ethan Gruber
	Date: January 2016
	Function: This XSLT style is intended to preprocess TEI files received from the vendor for the Mellon/NEH Ebook project
	before uploading into ETDPub. It performs the following tasks:
	
	* Adds an @xml:id to all divs
	* insert @xml:id to root element
	* move footnotes from within paragraphs to the end of a div, and order sequentially
	-->


<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:tei="http://www.tei-c.org/ns/1.0" xmlns:xlink="http://www.w3.org/1999/xlink"
	xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:openSearch="http://a9.com/-/spec/opensearchrss/1.0/" xmlns:eac="urn:isbn:1-931666-33-4" xmlns:gsx="http://schemas.google.com/spreadsheets/2006/extended"
	xmlns:skos="http://www.w3.org/2004/02/skos/core#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:mods="http://www.loc.gov/mods/v3"
	xmlns:schema="http://schema.org/" version="2.0" exclude-result-prefixes="#all">

	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>
	<xsl:strip-space elements="*"/>


	<xsl:variable name="filename" select="substring-before(replace(tokenize(base-uri(), '/')[last()], '%20', ' '), '.xml')"/>

	<!-- get the EBook listing from Google Spreadsheets -->
	<xsl:variable name="entry" as="node()*">
		<xsl:copy-of
			select="document('https://spreadsheets.google.com/feeds/list/1mvZu1JUw9mwusMAsDOaZGbDGEmBpfb9xhbeyqn2-Uk4/od6/public/full')//atom:entry[gsx:filename=$filename]"/>
	</xsl:variable>

	<!-- get MODS from Donum -->
	<xsl:variable name="mods" as="node()*">
		<xsl:copy-of select="document(concat('http://donum.numismatics.org/cgi-bin/koha/opac-export.pl?op=export&amp;format=mods&amp;bib=', $entry/gsx:donum))"/>
	</xsl:variable>

	<xsl:template match="@*|*|comment()">
		<xsl:copy>
			<xsl:apply-templates select="*|@*|text()|processing-instruction()|comment()"/>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="tei:TEI">
		<xsl:variable name="id">
			<xsl:choose>
				<xsl:when test="$entry/gsx:filename = 'Hispanic1-Part1'">
					<xsl:value-of select="concat('nnan', $entry/gsx:donum, '_1')"/>
				</xsl:when>
				<xsl:when test="$entry/gsx:filename = 'Hispanic1-Part2'">
					<xsl:value-of select="concat('nnan', $entry/gsx:donum, '_2')"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="concat('nnan', $entry/gsx:donum)"/>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:variable>		
		
		<xsl:result-document method="xml" href="final/{$id}.xml">
			<xsl:element name="TEI" namespace="http://www.tei-c.org/ns/1.0">
				<xsl:namespace name="xsi">http://www.w3.org/2001/XMLSchema-instance</xsl:namespace>
				<xsl:attribute name="xsi:noNamespaceSchemaLocation">http://www.tei-c.org/release/xml/tei/custom/schema/xsd/tei_all.xsd</xsl:attribute>
				<xsl:attribute name="xml:id" select="$id"/>
				<xsl:apply-templates/>
			</xsl:element>
		</xsl:result-document> 
	</xsl:template>

	<xsl:template match="tei:teiHeader">
		<teiHeader xmlns="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates/>
			<revisionDesc>
				<change when="{format-date(current-date(), '[Y]-[M01]-[D01]')}">Ran TEI file through XSLT process, detailed in the Mellon EBook migration Google Document.</change>
			</revisionDesc>
		</teiHeader>
	</xsl:template>

	<xsl:template match="tei:fileDesc">
		<fileDesc xmlns="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates select="tei:titleStmt|tei:publicationStmt"/>
			<xsl:if test="contains($filename, 'COAC') or contains($filename, 'NS') or contains($filename, 'NNM')">
				<seriesStmt>
					<title>
						<xsl:choose>
							<xsl:when test="contains($filename, 'COAC')">Coinage of the Americas Conference</xsl:when>
							<xsl:when test="contains($filename, 'NS')">Numismatic Studies</xsl:when>
							<xsl:when test="contains($filename, 'NNM')">Numismatic Notes and Monographs</xsl:when>
						</xsl:choose>
					</title>
					<biblScope unit="issue">
						<xsl:analyze-string select="$filename" regex="[A-Z]+(\d+)">

							<xsl:matching-substring>
								<xsl:value-of select="regex-group(1)"/>
							</xsl:matching-substring>
						</xsl:analyze-string>
					</biblScope>
					<idno type="URI">
						<xsl:choose>
							<xsl:when test="contains($filename, 'COAC')">http://numismatics.org/library/195780</xsl:when>
							<xsl:when test="contains($filename, 'NS')">http://numismatics.org/library/195779</xsl:when>
							<xsl:when test="contains($filename, 'NNM')">http://numismatics.org/library/195778</xsl:when>
						</xsl:choose>
					</idno>
				</seriesStmt>
			</xsl:if>
			<xsl:apply-templates select="tei:sourceDesc"/>
		</fileDesc>
	</xsl:template>

	<!-- TEI header -->
	<xsl:template match="tei:titleStmt">
		<titleStmt xmlns="http://www.tei-c.org/ns/1.0">
			<title>
				<xsl:value-of select="$mods/mods:mods/mods:titleInfo/mods:title"/>
			</title>
			<xsl:for-each select="$entry/*[starts-with(local-name(), 'author')][matches(., 'https?://')]|$entry/*[starts-with(local-name(), 'editor')][matches(., 'https?://')]">

				<xsl:element name="{substring(local-name(), 1, 6)}" namespace="http://www.tei-c.org/ns/1.0">
					<xsl:element name="name">
						<xsl:choose>
							<xsl:when test="contains(., 'numismatics.org')">
								<xsl:value-of select="document(concat(., '.xml'))//eac:nameEntry[1]/eac:part"/>
							</xsl:when>
							<xsl:when test="contains(., 'viaf.org')">
								<xsl:variable name="rdf" as="element()*">
									<xsl:copy-of select="document(concat(., '/rdf'))/*"/>
								</xsl:variable>

								<xsl:choose>
									<xsl:when test="$rdf//rdf:Description[skos:inScheme/@rdf:resource='http://viaf.org/authorityScheme/LC']/skos:prefLabel">
										<xsl:value-of select="$rdf//rdf:Description[skos:inScheme/@rdf:resource='http://viaf.org/authorityScheme/LC']/skos:prefLabel"/>
									</xsl:when>
									<xsl:otherwise>
										<xsl:value-of select="$rdf//skos:prefLabel[1]"/>
									</xsl:otherwise>
								</xsl:choose>
							</xsl:when>
						</xsl:choose>
					</xsl:element>
					<xsl:element name="idno">
						<xsl:attribute name="type">URI</xsl:attribute>
						<xsl:value-of select="."/>
					</xsl:element>
				</xsl:element>
			</xsl:for-each>
			<xsl:apply-templates select="tei:respStmt"/>
			
			<xsl:element name="funder" namespace="http://www.tei-c.org/ns/1.0">The Andrew W. Mellon Foundation</xsl:element>
		</titleStmt>
	</xsl:template>

	<!-- create bibls for Donum, Worldcat and Worldcat Works URIs -->
	<xsl:template match="tei:sourceDesc">
		<xsl:element name="sourceDesc" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:element name="bibl" namespace="http://www.tei-c.org/ns/1.0">
				<xsl:element name="title" namespace="http://www.tei-c.org/ns/1.0">Donum</xsl:element>
				<xsl:element name="idno" namespace="http://www.tei-c.org/ns/1.0">
					<xsl:attribute name="type">URI</xsl:attribute>
					<xsl:value-of select="concat('http://numismatics.org/library/', $entry/gsx:donum)"/>
				</xsl:element>
			</xsl:element>

			<xsl:if test="string($entry/gsx:oclc)">
				<xsl:element name="bibl" namespace="http://www.tei-c.org/ns/1.0">
					<xsl:element name="title" namespace="http://www.tei-c.org/ns/1.0">Worldcat</xsl:element>
					<xsl:element name="idno" namespace="http://www.tei-c.org/ns/1.0">
						<xsl:attribute name="type">URI</xsl:attribute>
						<xsl:value-of select="concat('http://www.worldcat.org/oclc/', $entry/gsx:oclc)"/>
					</xsl:element>
				</xsl:element>

				<xsl:if test="doc-available(concat('http://experiment.worldcat.org/oclc/', $entry/gsx:oclc, '.rdf'))">
					<xsl:element name="bibl" namespace="http://www.tei-c.org/ns/1.0">
						<xsl:element name="title" namespace="http://www.tei-c.org/ns/1.0">Worldcat Works</xsl:element>
						<xsl:element name="idno" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:attribute name="type">URI</xsl:attribute>
							<xsl:value-of select="document(concat('http://experiment.worldcat.org/oclc/', $entry/gsx:oclc, '.rdf'))//schema:exampleOfWork/@rdf:resource"/>
						</xsl:element>
					</xsl:element>
				</xsl:if>
			</xsl:if>
			
			<xsl:if test="string($entry/gsx:hathitrust)">
				<xsl:element name="bibl" namespace="http://www.tei-c.org/ns/1.0">
					<xsl:element name="title" namespace="http://www.tei-c.org/ns/1.0">HathiTrust</xsl:element>
					<xsl:element name="idno" namespace="http://www.tei-c.org/ns/1.0">
						<xsl:attribute name="type">URI</xsl:attribute>
						<xsl:value-of select="$entry/gsx:hathitrust"/>
					</xsl:element>
				</xsl:element>
			</xsl:if>
		</xsl:element>
	</xsl:template>

	<xsl:template match="tei:publicationStmt">
		<publicationStmt xmlns="http://www.tei-c.org/ns/1.0">
			<publisher>
				<name>American Numismatic Society</name>
				<idno type="URI">http://numismatics.org/authority/american_numismatic_society</idno>
			</publisher>
			<pubPlace>New York</pubPlace>
			<date>
				<xsl:value-of select="tei:date"/>
			</date>
			<availability>
				<licence target="http://creativecommons.org/licenses/by-nc/4.0/">
					<xsl:text>This work is made available under a Creative Commons Attribution-NonCommercial 4.0 International license</xsl:text>
				</licence>
			</availability>
		</publicationStmt>
	</xsl:template>

	<xsl:template match="tei:profileDesc">
		<profileDesc xmlns="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates/>

			<xsl:if test="string($entry/gsx:fieldofnumismatics)">
				<textClass>
					<keywords scheme="http://nomisma.org/">
						<xsl:for-each select="tokenize($entry/gsx:fieldofnumismatics, '\|')">
							<xsl:variable name="href" select="."/>

							<term ref="{$href}">
								<xsl:value-of select="document(concat($href, '.rdf'))//skos:prefLabel[@xml:lang='en']"/>
							</term>
						</xsl:for-each>
					</keywords>
				</textClass>
			</xsl:if>

		</profileDesc>
	</xsl:template>

	<!-- content -->
	<xsl:template match="*[starts-with(local-name(), 'div')]">
		<xsl:variable name="next-level" select="concat('div', string(number(substring(local-name(), 4, 1)) + 1))"/>
		
		<xsl:element name="{local-name()}" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:attribute name="xml:id" select="generate-id()"/>
			<xsl:apply-templates select="@*[not(name()='xml:id')]|*"/>

			<xsl:apply-templates select="child::tei:note[@place='foot']|descendant::tei:note[@place='foot'][not(ancestor::*[local-name()=$next-level])]" mode="move">
				<xsl:sort select="substring-after(@xml:id, '-n')" data-type="number"/>
			</xsl:apply-templates>
		</xsl:element>
	</xsl:template>
	
	<!-- restructure the TOC as a list into a table -->
	<xsl:template match="tei:list[parent::tei:div1[@type='contents' or @type='toc']]">
		<xsl:variable name="count" select="count(tei:item)"/>
		<xsl:variable name="list" as="element()*">
			<xsl:copy-of select="."/>
		</xsl:variable>
		
		<xsl:element name="table" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:for-each select="1 to $count">
				<xsl:variable name="position" select="position()"/>
				<xsl:if test="$position mod 2 = 0">
					<xsl:element name="row" namespace="http://www.tei-c.org/ns/1.0">
						<xsl:element name="cell" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:apply-templates select="$list//tei:item[$position - 1]" mode="toc"/>
						</xsl:element>
						<xsl:element name="cell" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:apply-templates select="$list//tei:item[$position]" mode="toc"/>
						</xsl:element>
					</xsl:element>
				</xsl:if>
			</xsl:for-each>
		</xsl:element>
	</xsl:template>
	
	<xsl:template match="tei:item" mode="toc">
		<xsl:apply-templates/>
	</xsl:template>

	<!-- suppress footnotes that are inside of paragraphs -->
	<xsl:template match="tei:note[@place='foot']"/>

	<xsl:template match="tei:note[@place='foot']" mode="move">
		<xsl:element name="note" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:attribute name="place">end</xsl:attribute>
			<xsl:attribute name="xml:id" select="@xml:id"/>
			<xsl:apply-templates/>
		</xsl:element>
	</xsl:template>

	<xsl:template match="tei:date/@value">
		<xsl:attribute name="when">
			<xsl:value-of select="."/>
		</xsl:attribute>
	</xsl:template>
</xsl:stylesheet>
