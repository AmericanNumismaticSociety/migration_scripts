<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:nuds="http://nomisma.org/nuds" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xlink="http://www.w3.org/1999/xlink"
	version="2.0">
	<xsl:strip-space elements="*"/>
	<xsl:output indent="yes" encoding="UTF-8"/>
	
	<!-- generate parent type by stripping out some typological information and measurements -->	
	<xsl:template match="/">		
		<xsl:for-each select="collection('file:///home/komet/ans_migration/wwi/nuds/?select=*.xml')">
			<xsl:variable name="filename">
				<xsl:variable name="pieces" select="tokenize(tokenize(replace(document-uri(.), '.xml', ''), '/')[last()], '\.')"/>
				<xsl:for-each select="$pieces[not(position()=last())]">
					<xsl:value-of select="."/>
					<xsl:if test="not(position()=last())">
						<xsl:text>.</xsl:text>
					</xsl:if>
				</xsl:for-each>
			</xsl:variable>
			<xsl:result-document href="new/{$filename}.xml">
				<xsl:apply-templates select="document(document-uri(.))/@*|node()" mode="transform"/>
			</xsl:result-document>			
		</xsl:for-each>
	</xsl:template>
	
	<xsl:template match="@*|node()" mode="transform">
		<xsl:copy>
			<xsl:apply-templates select="@*|node()" mode="transform"/>
		</xsl:copy>
	</xsl:template>
	
	<xsl:template match="nuds:recordId|nuds:title" mode="transform">
		<xsl:element name="{local-name()}" namespace="http://nomisma.org/nuds">
			<xsl:if test="@xml:lang">
				<xsl:attribute name="xml:lang" select="@xml:lang"/>
			</xsl:if>
			<xsl:variable name="pieces" select="tokenize(., '\.')"/>
			<xsl:for-each select="$pieces[not(position()=last())]">
				<xsl:value-of select="."/>
				<xsl:if test="not(position()=last())">
					<xsl:text>.</xsl:text>
				</xsl:if>
			</xsl:for-each>
		</xsl:element>
	</xsl:template>
	
	<xsl:template match="nuds:publicationStatus" mode="transform">
		<xsl:element name="publicationStatus" namespace="http://nomisma.org/nuds">
			<xsl:text>approved</xsl:text>
		</xsl:element>
	</xsl:template>
	
	<xsl:template match="nuds:measurementsSet|nuds:objectType|nuds:material|nuds:manufacture|nuds:otherRecordId" mode="transform"/>
	
</xsl:stylesheet>
