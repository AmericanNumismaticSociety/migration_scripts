<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:tei="http://www.tei-c.org/ns/1.0" xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:gsx="http://schemas.google.com/spreadsheets/2006/extended" exclude-result-prefixes="#all" version="2.0">
	<xsl:strip-space elements="*"/>
	<xsl:output encoding="UTF-8" indent="yes" method="xml"/>

	<xsl:variable name="filename" select="substring-after(substring-before(replace(tokenize(base-uri(), '/')[last()], '%20', ' '), '.xml'), 'nnan')"/>

	<!-- get the EBook listing from Google Spreadsheets -->
	<xsl:variable name="entry" as="node()*">
		<xsl:copy-of select="document('https://spreadsheets.google.com/feeds/list/1mvZu1JUw9mwusMAsDOaZGbDGEmBpfb9xhbeyqn2-Uk4/od6/public/full')//atom:entry[gsx:donum=$filename]"/>
	</xsl:variable>

	<xsl:template match="@*|node()">
		<xsl:copy>
			<xsl:apply-templates select="@*|node()"/>
		</xsl:copy>
	</xsl:template>
	
	<xsl:template match="tei:sourceDesc">
		<xsl:element name="sourceDesc" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates/>
			
			<xsl:if test="string($entry/gsx:hathitrust)">
				<xsl:variable name="pieces" select="tokenize($entry/gsx:hathitrust, '\|')"/>
				
				<xsl:for-each select="$pieces">
					<xsl:element name="bibl" namespace="http://www.tei-c.org/ns/1.0">
						<xsl:element name="title" namespace="http://www.tei-c.org/ns/1.0">HathiTrust</xsl:element>
						<xsl:element name="idno" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:attribute name="type">URI</xsl:attribute>
							<xsl:value-of select="."/>
						</xsl:element>
					</xsl:element>
				</xsl:for-each>				
			</xsl:if>
		</xsl:element>
	</xsl:template>
	
	<xsl:template match="tei:titleStmt">
		<xsl:element name="titleStmt" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates/>
			
			<xsl:element name="funder" namespace="http://www.tei-c.org/ns/1.0">The Andrew W. Mellon Foundation</xsl:element>
		</xsl:element>
	</xsl:template>

	<xsl:template match="tei:revisionDesc">
		<xsl:element name="revisionDesc" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates/>
			<xsl:element name="change" namespace="http://www.tei-c.org/ns/1.0">
				<xsl:attribute name="when" select="format-date(current-date(), '[Y]-[M01]-[D01]')"/>
				<xsl:text>Reprocessed: restructured TOC list into table; added HathiTrust URI; added funder</xsl:text>
			</xsl:element>
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
</xsl:stylesheet>
