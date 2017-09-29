<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns="http://www.tei-c.org/ns/1.0"
	xmlns:tei="http://www.tei-c.org/ns/1.0" exclude-result-prefixes="xs" version="3.0">
	<xsl:strip-space elements="*"/>
	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>

	<xsl:template match="@* | node()">
		<xsl:copy>
			<xsl:apply-templates select="@* | node()"/>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="tei:teiHeader">
		<xsl:element name="teiHeader" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates/>
			<xsl:element name="revisionDesc" namespace="http://www.tei-c.org/ns/1.0">
				<xsl:element name="change" namespace="http://www.tei-c.org/ns/1.0">
					<xsl:attribute name="when" select="substring(string(current-date()), 1, 10)"></xsl:attribute>
					<xsl:text>Reprocessed TEI for IIIF compliance on service URI and annotation coordinates.</xsl:text>
				</xsl:element>
			</xsl:element>
		</xsl:element>
	</xsl:template>

	<!-- remove keywords/terms, which are redundant -->
	<xsl:template match="tei:profileDesc"/>

	<xsl:template match="tei:facsimile">

		<xsl:variable name="url" select="concat('http://images.numismatics.org/archivesimages%2Farchive%2F', tei:graphic/@url, '.jpg/info.json')"/>
		<xsl:variable name="jsonstr" select="string(unparsed-text($url))"/>

		<xsl:variable name="info" as="node()*">
			<xsl:copy-of select="json-to-xml($jsonstr)"/>
		</xsl:variable>

		<xsl:variable name="height" select="$info//*:number[@key = 'height']"/>
		<xsl:variable name="width" select="$info//*:number[@key = 'width']"/>

		<xsl:element name="facsimile" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:attribute name="xml:id" select="@xml:id"/>
			<xsl:apply-templates select="tei:graphic">
				<xsl:with-param name="height" select="$height"/>
				<xsl:with-param name="width" select="$width"/>
			</xsl:apply-templates>
			<xsl:apply-templates select="tei:surface">
				<xsl:with-param name="height" select="$height"/>
				<xsl:with-param name="width" select="$width"/>
			</xsl:apply-templates>
		</xsl:element>
	</xsl:template>

	<xsl:template match="tei:graphic">
		<xsl:param name="height"/>
		<xsl:param name="width"/>

		<xsl:element name="media" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:attribute name="url" select="concat('http://images.numismatics.org/archivesimages%2Farchive%2F', @url, '.jpg')"/>
			<xsl:if test="string(@n)">
				<xsl:attribute name="n" select="@n"/>
			</xsl:if>			
			<xsl:attribute name="mimeType">image/jpeg</xsl:attribute>
			<xsl:attribute name="type">IIIFService</xsl:attribute>
			<xsl:attribute name="height" select="concat($height, 'px')"/>
			<xsl:attribute name="width" select="concat($width, 'px')"/>
		</xsl:element>
	</xsl:template>

	<xsl:template match="tei:surface">
		<xsl:param name="height"/>
		<xsl:param name="width"/>

		<xsl:variable name="ratio"/>

		<xsl:element name="surface" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:attribute name="xml:id" select="@xml:id"/>

			<xsl:choose>
				<xsl:when test="$height &gt;= $width">
					<xsl:variable name="ratio" select="$height div $width"/>
					<xsl:variable name="h"
						select="ceiling((($height div 2) * (@uly div $ratio)) + ($height div 2)) - ceiling((($height div 2) * (@lry div $ratio)) + ($height div 2))"/>

					<xsl:attribute name="ulx" select="ceiling(($width div 2) + (($width div 2) * @ulx))"/>
					<xsl:attribute name="uly">
						<xsl:choose>
							<xsl:when test="@uly &lt; 0">
								<xsl:value-of select="abs(ceiling((-($height div 2) * (@uly div -$ratio)) - ($height div 2))) - $h"/>
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="$height - ceiling((($height div 2) * (@uly div $ratio)) + ($height div 2)) - $h"/>
							</xsl:otherwise>
						</xsl:choose>
					</xsl:attribute>
					<xsl:attribute name="lrx" select="ceiling(($width div 2) + (($width div 2) * @lrx))"/>
					<xsl:attribute name="lry">
						<xsl:choose>
							<xsl:when test="@uly &lt; 0">
								<xsl:value-of select="abs(ceiling((-($height div 2) * (@uly div -$ratio)) - ($height div 2)))"/>
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="$height - ceiling((($height div 2) * (@uly div $ratio)) + ($height div 2))"/>
							</xsl:otherwise>
						</xsl:choose>
					</xsl:attribute>
					<xsl:apply-templates/>
				</xsl:when>
			</xsl:choose>
		</xsl:element>
	</xsl:template>

</xsl:stylesheet>
