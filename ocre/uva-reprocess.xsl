<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:nuds="http://nomisma.org/nuds" xmlns:xlink="http://www.w3.org/1999/xlink" exclude-result-prefixes="#all"
	version="2.0">
	<xsl:strip-space elements="*"/>
	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>
	
	<xsl:variable name="authority" select="//nuds:persname[@xlink:role='authority']"/>
	<xsl:variable name="authCode">
		<xsl:choose>
			<xsl:when test="$authority = 'Postumus'">post</xsl:when>
			<xsl:when test="$authority = 'Victorinus'">vict</xsl:when>
			<xsl:when test="$authority = 'Marius'">mar</xsl:when>
			<xsl:when test="$authority = 'Tetricus I'">tet_i</xsl:when>
			<xsl:when test="$authority = 'Tetricus II'">tet_i</xsl:when>
		</xsl:choose>
	</xsl:variable>
	
	<xsl:template match="@*|node()">
		<xsl:copy>
			<xsl:apply-templates select="@*|node()"/>
		</xsl:copy>
	</xsl:template>
	
	<xsl:template match="nuds:control">
		<xsl:element name="control" namespace="http://nomisma.org/nuds">
			<xsl:apply-templates/>
			
			<xsl:if test="not(nuds:semanticDeclaration[nuds:prefix='nmo'])">
				<semanticDeclaration xmlns="http://nomisma.org/nuds">
					<prefix>nmo</prefix>
					<namespace>http://nomisma.org/ontology#</namespace>
				</semanticDeclaration>
			</xsl:if>
		</xsl:element>
	</xsl:template>
	
	<xsl:template match="nuds:reference[nuds:title='RIC']">
		<xsl:choose>
			<xsl:when test="string($authCode)">
				<xsl:variable name="uri" select="concat('http://numismatics.org/ocre/id/ric.5.', $authCode, '.', nuds:identifier)"/>
				
				<xsl:choose>
					<xsl:when test="doc-available(concat($uri, '.xml'))">
						<reference xmlns="http://nomisma.org/nuds" xlink:type="simple" xlink:arcrole="nmo:hasTypeSeriesItem" xlink:href="{$uri}">
							<xsl:value-of select="document(concat($uri, '.xml'))//nuds:title"/>
						</reference>
					</xsl:when>
					<xsl:otherwise>
						<xsl:copy-of select="."/>
					</xsl:otherwise>
				</xsl:choose>				
			</xsl:when>
			<xsl:otherwise>
				<xsl:copy-of select="."/>
			</xsl:otherwise>
		</xsl:choose>		
	</xsl:template>
	
	<xsl:template match="nuds:persname[@xlink:role='authority'][. = 'Tetricus II']">
		<persname xmlns="http://nomisma.org/nuds" xlink:type="simple" xlink:role="authority" xlink:href="http://nomisma.org/id/tetricus_i">Tetricus I</persname>
	</xsl:template>
</xsl:stylesheet>