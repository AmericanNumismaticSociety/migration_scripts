<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xlink="http://www.w3.org/1999/xlink"
	xmlns:xs="http://www.w3.org/2001/XMLSchema" exclude-result-prefixes="xsl xs mods xlink" version="2.0">
	<xsl:strip-space elements="*"/>
	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>

	<xsl:variable name="recordId" select="//mods:recordIdentifier"/>

	<xsl:variable name="file" as="node()*">
		<xsl:copy-of select="document('mods-rights/data/photos.xml')//file[@id=$recordId]"/>
	</xsl:variable>
	
	<xsl:variable name="id" select="$file/id"/>

	<xsl:template match="@* | node()">
		<xsl:copy copy-namespaces="no">
			<xsl:apply-templates select="@* | node()"/>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="mods:mods">
		<mods version="3.6" xmlns="http://www.loc.gov/mods/v3">
			<xsl:apply-templates/>

			<!-- insert rights statement -->
			<accessCondition type="rights">
				<url>
					<xsl:value-of select="$file/rights"/>
				</url>
			</accessCondition>
		</mods>
	</xsl:template>
	
	<!-- replace ID -->
	<xsl:template match="mods:identifier">
		<identifier xmlns="http://www.loc.gov/mods/v3">
			<xsl:value-of select="$id"/>
		</identifier>
	</xsl:template>

	<xsl:template match="mods:location[mods:url]">
		<location xmlns="http://www.loc.gov/mods/v3">
			<url access="raw object" note="IIIFService">
				<xsl:value-of select="concat('http://images.numismatics.org/archivesimages%2Farchive%2F', $id, '.jpg')"/>
			</url>
			<url access="preview">
				<xsl:value-of select="concat('http://numismatics.org/archivesimages/thumbnail/', $id, '.jpg')"/>
			</url>
			<url usage="primary display">
				<xsl:value-of select="concat('http://numismatics.org/archivesimages/reference/', $id, '.jpg')"/>
			</url>
		</location>
	</xsl:template>

	<!-- suppress top level origin info: date is in the relatedItem -->
	<xsl:template match="mods:mods/mods:originInfo"/>

	<xsl:template match="mods:relatedItem">
		<relatedItem type="original" xmlns="http://www.loc.gov/mods/v3">
			<xsl:apply-templates/>
		</relatedItem>
	</xsl:template>

	<xsl:template match="mods:recordInfo">
		<recordInfo xmlns="http://www.loc.gov/mods/v3">
			<xsl:apply-templates/>
			<recordChangeDate encoding="iso8601">
				<xsl:value-of select="substring(string(current-date()), 1, 10)"/>
			</recordChangeDate>
		</recordInfo>
	</xsl:template>

</xsl:stylesheet>
