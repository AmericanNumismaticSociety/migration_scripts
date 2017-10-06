<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:mods="http://www.loc.gov/mods/v3"
	exclude-result-prefixes="#all" version="3.0">
	<xsl:strip-space elements="*"/>
	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>

	<xsl:template match="/">
		<files>
			<xsl:for-each select="collection('/usr/local/projects/archival-xml/mods?select=*.xml')">
				<xsl:variable name="file" select="document-uri(.)"/>

				<xsl:apply-templates select="document($file)//mods:mods"/>
			</xsl:for-each>
		</files>

	</xsl:template>

	<xsl:template match="mods:mods">
		<file id="{//mods:recordIdentifier}">
			<title>
				<xsl:value-of select="mods:titleInfo/mods:title"/>
			</title>
			<date>
				<xsl:value-of select="mods:originInfo/mods:dateCreated"/>
			</date>
			<id>
				<xsl:value-of select="mods:identifier"/>
			</id>
			<thumbnail>
				<xsl:value-of select="mods:location/mods:url[@access = 'preview']"/>
			</thumbnail>
			<rights/>
		</file>
	</xsl:template>

</xsl:stylesheet>
