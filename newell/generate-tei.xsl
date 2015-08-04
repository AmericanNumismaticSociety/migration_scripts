<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:mods="http://www.loc.gov/mods/v3" xmlns="http://www.tei-c.org/ns/1.0"
	exclude-result-prefixes="xs mods" version="2.0">

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
						<author>Newell, Edward Theodore, 1886-1941</author>
					</titleStmt>
					<publicationStmt>
						<publisher>American Numismatic Society</publisher>
						<pubPlace>New York (N.Y.)</pubPlace>
						<idno type="donum">
							<xsl:value-of select="$id"/>
						</idno>
					</publicationStmt>
					<notesStmt>
						<note type="abstract">
							<p>
								<xsl:value-of select="mods:abstract"/>
							</p>
						</note>
					</notesStmt>
					<sourceDesc>
						<biblStruct>
							<monogr>
								<author>Newell, Edward Theodore, 1886-1941</author>
								<title>
									<xsl:value-of select="mods:titleInfo/mods:title"/>
								</title>
								<imprint>
									<xsl:choose>
										<xsl:when test="mods:originInfo/mods:dateIssued[@point='start'] and mods:originInfo/mods:dateIssued[@point='end']">
											<date from="{mods:originInfo/mods:dateIssued[@point='start']}" to="{mods:originInfo/mods:dateIssued[@point='end']}">
												<xsl:value-of select="mods:originInfo/mods:dateIssued[@point='start']"/>
												<xsl:text>-</xsl:text>
												<xsl:value-of select="mods:originInfo/mods:dateIssued[@point='end']"/>
											</date>
										</xsl:when>
										<xsl:when test="mods:originInfo/mods:dateIssued[@point='start'] and not(mods:originInfo/mods:dateIssued[@point='end'])">
											<date when="{mods:originInfo/mods:dateIssued[@point='start']}">
												<xsl:value-of select="mods:originInfo/mods:dateIssued[@point='start']"/>
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
					<textClass>
						<xsl:for-each select="document('list/files.xml')//file[contains(., $id)]">
							<keywords corresp="nnan{.}"/>
						</xsl:for-each>
					</textClass>
				</profileDesc>				
			</teiHeader>
			<xsl:for-each select="document('list/files.xml')//file[contains(., $id)]">
				<facsimile xml:id="nnan{.}">
					<graphic url="{.}"/>
				</facsimile>
			</xsl:for-each>
		</TEI>
	</xsl:template>
</xsl:stylesheet>
