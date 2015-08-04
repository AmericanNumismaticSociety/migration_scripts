<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns="urn:isbn:1-931666-33-4" xmlns:eac="urn:isbn:1-931666-33-4" xmlns:xs="http://www.w3.org/2001/XMLSchema"
	xmlns:xlink="http://www.w3.org/1999/xlink" version="2.0" exclude-result-prefixes="xs eac">

	<xsl:strip-space elements="*"/>
	<xsl:output encoding="UTF-8" indent="yes"/>


	<xsl:template match="@*|node()">
		<xsl:copy>
			<xsl:apply-templates select="@*|node()"/>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="eac:otherRecordId"/>

	<xsl:template match="eac:localTypeDeclaration"/>

	<xsl:template match="eac:identity">
		<xsl:element name="identity" namespace="urn:isbn:1-931666-33-4">
			<xsl:for-each select="//eac:otherRecordId[@localType='owl:sameAs']">
				<xsl:element name="entityId" namespace="urn:isbn:1-931666-33-4">
					<xsl:attribute name="localType">skos:exactMatch</xsl:attribute>
					<xsl:value-of select="."/>
				</xsl:element>
			</xsl:for-each>
			<xsl:apply-templates/>
		</xsl:element>
	</xsl:template>

	<xsl:template match="eac:control">
		<xsl:element name="control" namespace="urn:isbn:1-931666-33-4">
			<xsl:apply-templates/>

			<localTypeDeclaration>
				<abbreviation>dcterms</abbreviation>
				<citation xlink:role="semantic" xlink:type="simple" xlink:href="http://purl.org/dc/terms/">http://purl.org/dc/terms/</citation>
			</localTypeDeclaration>
			<localTypeDeclaration>
				<abbreviation>foaf</abbreviation>
				<citation xlink:role="semantic" xlink:type="simple" xlink:href="http://xmlns.com/foaf/0.1/">http://xmlns.com/foaf/0.1/</citation>
			</localTypeDeclaration>
			<localTypeDeclaration>
				<abbreviation>org</abbreviation>
				<citation xlink:role="semantic" xlink:type="simple" xlink:href="http://www.w3.org/ns/org#">http://www.w3.org/ns/org#</citation>
			</localTypeDeclaration>
			<localTypeDeclaration>
				<abbreviation>rel</abbreviation>
				<citation xlink:role="semantic" xlink:type="simple" xlink:href="http://purl.org/vocab/relationship/">http://purl.org/vocab/relationship/</citation>
			</localTypeDeclaration>
			<localTypeDeclaration>
				<abbreviation>skos</abbreviation>
				<citation xlink:role="semantic" xlink:type="simple" xlink:href="http://www.w3.org/2004/02/skos/core#">http://www.w3.org/2004/02/skos/core#</citation>
			</localTypeDeclaration>
			<localTypeDeclaration>
				<abbreviation>xeac</abbreviation>
				<citation xlink:type="simple" xlink:role="semantic" xlink:href="https://github.com/ewg118/xEAC#">https://github.com/ewg118/xEAC#</citation>
			</localTypeDeclaration>
		</xsl:element>
	</xsl:template>

	<xsl:template match="eac:maintenanceHistory">
		<xsl:element name="maintenanceHistory" namespace="urn:isbn:1-931666-33-4">
			<xsl:apply-templates/>
			<maintenanceEvent>
				<eventType>revised</eventType>
				<eventDateTime standardDateTime="{concat(replace(string(current-dateTime()), '-04:00', ''), 'Z')}"/>
				<agentType>machine</agentType>
				<agent>XSLT</agent>
				<eventDescription>Reprocessed EAC-CPF documents into the new and more semantically aware relationship model; moved otherRecordIds into entityIds with skos:exactMatch @localType.</eventDescription>
			</maintenanceEvent>
		</xsl:element>
	</xsl:template>

	<xsl:template match="@xlink:role">
		<xsl:attribute name="xlink:role">
			<xsl:choose>
				<xsl:when test=". = 'http://RDVocab.info/uri/schema/FRBRentitiesRDA/CorporateBody'">org:Organization</xsl:when>
				<xsl:when test=". = 'http://RDVocab.info/uri/schema/FRBRentitiesRDA/Person'">foaf:Person</xsl:when>
				<xsl:when test=". = 'http://RDVocab.info/uri/schema/FRBRentitiesRDA/Family'">arch:Family</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="."/>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:attribute>
	</xsl:template>

</xsl:stylesheet>
