<?xml version="1.0" encoding="UTF-8"?>

<!-- Author: Ethan Gruber
	Date: January 2016
	Function: This XSLT style is intended to preprocess TEI files received from the vendor for the Mellon/NEH Ebook project
	before uploading into ETDPub. It performs the following tasks:
	
	* Adds an @xml:id to all divs
	* insert @xml:id to root element
	* move footnotes from within paragraphs to the end of a div, and order sequentially
	-->


<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:tei="http://www.tei-c.org/ns/1.0" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xs="http://www.w3.org/2001/XMLSchema"
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:openSearch="http://a9.com/-/spec/opensearchrss/1.0/" xmlns:eac="urn:isbn:1-931666-33-4"
	xmlns:gsx="http://schemas.google.com/spreadsheets/2006/extended" xmlns:skos="http://www.w3.org/2004/02/skos/core#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
	xmlns:mods="http://www.loc.gov/mods/v3" xmlns:schema="http://schema.org/" version="2.0" exclude-result-prefixes="#all">

	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>
	<xsl:strip-space elements="*"/>


	<xsl:variable name="filename" select="substring-before(replace(tokenize(base-uri(), '/')[last()], '%20', ' '), '.xml')"/>

	<!-- get the EBook listing from Google Spreadsheets -->
	<xsl:variable name="entry" as="node()*">
		<xsl:copy-of select="document('https://spreadsheets.google.com/feeds/list/1mvZu1JUw9mwusMAsDOaZGbDGEmBpfb9xhbeyqn2-Uk4/od6/public/full')//atom:entry[gsx:filename=$filename]"/>
	</xsl:variable>

	<!-- get MODS from Donum -->
	<xsl:variable name="mods" as="node()*">
		<xsl:copy-of select="document(concat('http://donum.numismatics.org/cgi-bin/koha/opac-export.pl?op=export&amp;bib=', $entry/gsx:donum, '&amp;format=mods'))"/>
	</xsl:variable>

	<xsl:template match="@*|*|comment()">
		<xsl:copy>
			<xsl:apply-templates select="*|@*|text()|processing-instruction()|comment()"/>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="tei:TEI">
		<xsl:element name="TEI" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:namespace name="xsi">http://www.w3.org/2001/XMLSchema-instance</xsl:namespace>
			<xsl:attribute name="xsi:noNamespaceSchemaLocation">http://www.tei-c.org/release/xml/tei/custom/schema/xsd/tei_all.xsd</xsl:attribute>
			<xsl:attribute name="xml:id" select="concat('nnan', $entry/gsx:donum)"/>
			<xsl:apply-templates/>
		</xsl:element>
	</xsl:template>
	
	<xsl:template match="tei:teiHeader">
		<teiHeader xmlns="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates/>
			<revisionDesc>
				<change when="{format-date(current-date(), '[Y]-[M01]-[D01]')}">Ran TEI file through XSLT process, detailed in the Mellon EBook migration Google Document.</change>
			</revisionDesc>
		</teiHeader>
	</xsl:template>

	<!-- TEI header -->
	<xsl:template match="tei:titleStmt">
		<titleStmt xmlns="http://www.tei-c.org/ns/1.0">
			<title>
				<xsl:value-of select="$mods/mods:mods/mods:titleInfo/mods:title"/>
			</title>
			<xsl:for-each select="$entry/*[starts-with(local-name(), 'author')][matches(., 'https?://')]">
				<author>
					<name>
						<xsl:choose>
							<xsl:when test="contains(., 'numismatics.org')">
								<xsl:value-of select="document(concat(., '.xml'))//eac:nameEntry[1]/eac:part"/>
							</xsl:when>
							<xsl:when test="contains(., 'viaf.org')">
								<xsl:value-of select="document(concat(., '/rdf'))//rdf:Description[skos:inScheme/@rdf:resource='http://viaf.org/authorityScheme/LC']/skos:prefLabel"/>
							</xsl:when>
						</xsl:choose>
					</name>
					<idno type="URI">
						<xsl:value-of select="."/>
					</idno>
				</author>
			</xsl:for-each>
			<xsl:apply-templates select="tei:respStmt"/>
		</titleStmt>
	</xsl:template>
	
	<!-- create bibls for Donum, Worldcat and Worldcat Works URIs -->
	<xsl:template match="tei:sourceDesc">
		<xsl:element name="sourceDesc" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:element name="bibl" namespace="http://www.tei-c.org/ns/1.0">
				<xsl:element name="idno" namespace="http://www.tei-c.org/ns/1.0">
					<xsl:attribute name="type">URI</xsl:attribute>
					<xsl:value-of select="concat('http://numismatics.org/library/', $entry/gsx:donum)"/>
				</xsl:element>
			</xsl:element>
			
			<xsl:if test="string($entry/gsx:oclc)">
				<xsl:element name="bibl" namespace="http://www.tei-c.org/ns/1.0">
					<xsl:element name="idno" namespace="http://www.tei-c.org/ns/1.0">
						<xsl:attribute name="type">URI</xsl:attribute>
						<xsl:value-of select="concat('http://www.worldcat.org/oclc/', $entry/gsx:oclc)"/>
					</xsl:element>
				</xsl:element>
				
				<xsl:element name="bibl" namespace="http://www.tei-c.org/ns/1.0">
					<xsl:element name="idno" namespace="http://www.tei-c.org/ns/1.0">
						<xsl:attribute name="type">URI</xsl:attribute>
						<xsl:value-of select="document(concat('http://experiment.worldcat.org/oclc/', $entry/gsx:oclc, '.rdf'))//schema:exampleOfWork/@rdf:resource"/>
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
				<licence target="http://creativecommons.org/licenses/by-nc/4.0/">This work is made available under a Creative Commons Attribution-NonCommercial 4.0 International license</licence>
			</availability>
		</publicationStmt>
	</xsl:template>

	<!-- content -->
	<xsl:template match="*[starts-with(local-name(), 'div')]">
		<xsl:element name="{local-name()}" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:attribute name="xml:id" select="generate-id()"/>
			<xsl:apply-templates select="@*[not(name()='xml:id')]|*"/>

			<xsl:apply-templates select="child::tei:note[@place='foot']|descendant::tei:note[@place='foot']" mode="move">
				<xsl:sort select="substring-after(@xml:id, '-n')" data-type="number"/>
			</xsl:apply-templates>
		</xsl:element>
	</xsl:template>

	<!-- suppress footnotes that are inside of paragraphs -->
	<xsl:template match="tei:note[@place='foot']"/>

	<xsl:template match="tei:note[@place='foot']" mode="move">
		<xsl:copy-of select="self::node()"/>
	</xsl:template>
</xsl:stylesheet>
