<?xml version="1.0" encoding="UTF-8"?>

<!--Author: Ethan Gruber 
	Function: To generate TEI files for Newell notebooks from MODS files extracted from Donum.
		The XSLT reads an XML file list to generate the list of facsimile elements
		It also incorporates templates from reprocess-iiif.xsl to read the height and width from the IIIF image service (info.json). XSLT 3.0 is required for processing JSON.
	Requires: Saxon with XSLT 3.0 support
	Last Modified: May 2018	
-->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:mods="http://www.loc.gov/mods/v3"
	xmlns="http://www.tei-c.org/ns/1.0" exclude-result-prefixes="xs mods" version="3.0">

	<xsl:strip-space elements="*"/>
	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>

	<xsl:template match="/">
		<xsl:for-each select="collection('file:///home/komet/ans_migration/newell/mods?select=*.xml')">
			<xsl:variable name="id" select="substring-before(substring-after(document-uri(.), 'mods/'), '.xml')"/>
			<xsl:result-document href="tei/nnan{$id}.xml" indent="yes" method="xml" encoding="utf-8">
				<xsl:call-template name="process">
					<xsl:with-param name="uri" select="document-uri(.)"/>
				</xsl:call-template>
			</xsl:result-document>
		</xsl:for-each>
	</xsl:template>

	<xsl:template name="process">
		<xsl:param name="uri"/>

		<xsl:apply-templates select="document($uri)/*">
			<xsl:with-param name="id" select="substring-before(substring-after($uri, 'mods/'), '.xml')"/>
		</xsl:apply-templates>
	</xsl:template>

	<xsl:template match="mods:mods">
		<xsl:param name="id"/>
		<TEI xmlns="http://www.tei-c.org/ns/1.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xml:id="nnan{$id}"
			xsi:schemaLocation="http://www.tei-c.org/ns/1.0 http://www.tei-c.org/release/xml/tei/custom/schema/xsd/tei_all.xsd">
			<teiHeader>
				<fileDesc>
					<titleStmt>
						<title>
							<xsl:value-of select="mods:titleInfo/mods:title"/>
						</title>
						<author>
							<persName ref="http://numismatics.org/authority/newell">Newell, Edward Theodore, 1886-1941</persName>
						</author>
					</titleStmt>
					<publicationStmt>
						<publisher>American Numismatic Society</publisher>
						<pubPlace>New York (N.Y.)</pubPlace>
						<idno type="donum">
							<xsl:value-of select="$id"/>
						</idno>
					</publicationStmt>					
					<sourceDesc>
						<biblStruct>
							<monogr>
								<author>
									<persName ref="http://numismatics.org/authority/newell">Newell, Edward Theodore, 1886-1941</persName>
								</author>
								<title>
									<xsl:value-of select="mods:titleInfo/mods:title"/>
								</title>
								<imprint>
									<xsl:choose>
										<xsl:when test="mods:originInfo/mods:dateIssued[@point = 'start'] and mods:originInfo/mods:dateIssued[@point = 'end']">
											<date from="{mods:originInfo/mods:dateIssued[@point='start']}" to="{mods:originInfo/mods:dateIssued[@point='end']}">
												<xsl:value-of select="mods:originInfo/mods:dateIssued[@point = 'start']"/>
												<xsl:text>-</xsl:text>
												<xsl:value-of select="mods:originInfo/mods:dateIssued[@point = 'end']"/>
											</date>
										</xsl:when>
										<xsl:when
											test="mods:originInfo/mods:dateIssued[@point = 'start'] and not(mods:originInfo/mods:dateIssued[@point = 'end'])">
											<date when="{mods:originInfo/mods:dateIssued[@point='start']}">
												<xsl:value-of select="mods:originInfo/mods:dateIssued[@point = 'start']"/>
											</date>
										</xsl:when>
										<xsl:otherwise>
											<date>unknown</date>
										</xsl:otherwise>
									</xsl:choose>
								</imprint>
								<extent>
									<xsl:value-of select="mods:physicalDescription/mods:extent"/>
								</extent>
							</monogr>
						</biblStruct>
					</sourceDesc>
				</fileDesc>
				<profileDesc>
					<abstract>
						<p>
							<xsl:value-of select="mods:abstract"/>
						</p>
					</abstract>
					<textClass>
						<classCode scheme="http://vocab.getty/edu/aat/">300264354</classCode>
					</textClass>
				</profileDesc>
				<revisionDesc>
					<change when="{current-date()}">Generated TEI-XML document from MODS, image file list, and IIIF service lookups</change>
				</revisionDesc>
			</teiHeader>
			<xsl:for-each select="document('list/files.xml')//file[contains(., $id)]">
				<xsl:variable name="service" select="concat('http://images.numismatics.org/archivesimages%2Farchive%2F', ., '.jpg')"/>
				<xsl:variable name="jsonstr" select="string(unparsed-text(concat($service, '/info.json')))"/>

				<xsl:variable name="info" as="node()*">
					<xsl:copy-of select="json-to-xml($jsonstr)"/>
				</xsl:variable>

				<xsl:variable name="height" select="$info//*:number[@key = 'height']"/>
				<xsl:variable name="width" select="$info//*:number[@key = 'width']"/>


				<facsimile xml:id="nnan{.}">
					<media url="{$service}" mimeType="image/jpeg" type="IIIFService">
						<xsl:attribute name="height" select="concat($height, 'px')"/>
						<xsl:attribute name="width" select="concat($width, 'px')"/>
					</media>
					<!--<graphic url="{.}"/>-->
				</facsimile>
			</xsl:for-each>
		</TEI>
	</xsl:template>
</xsl:stylesheet>
