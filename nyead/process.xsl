<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:ead="urn:isbn:1-931666-22-9" xmlns:my="http://example.org/my" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xlink="http://www.w3.org/1999/xlink"  xmlns:xs="http://www.w3.org/2001/XMLSchema"
	exclude-result-prefixes="xsl my" version="2.0">
	<xsl:output encoding="UTF-8" method="xml" indent="yes"/>
	<xsl:strip-space elements="*"/>
	<xsl:template match="@*|node()">
		<xsl:copy>
			<xsl:apply-templates select="@*|node()"/>
		</xsl:copy>		
	</xsl:template>

	<!-- suppress processing instructions, comments -->
	<xsl:template match="processing-instruction()|comment()"/>

	<!-- result document for matching eadid with filename -->
	
	<xsl:template match="*[local-name()='ead']">
		<!--<xsl:result-document href="{$agencyCode}/{$eadid}.xml">
			<xsl:element name="ead" namespace="urn:isbn:1-931666-22-9" xmlns:xlink="http://www.w3.org/1999/xlink">
				<xsl:namespace name="xlink">http://www.w3.org/1999/xlink</xsl:namespace>
				<xsl:choose>
					<xsl:when test="not(@xsi:noNamespaceSchemaLocation)">
						<xsl:namespace name="xsi">http://www.w3.org/2001/XMLSchema-instance</xsl:namespace>
						<xsl:attribute name="xsi:schemaLocation">urn:isbn:1-931666-22-9 http://www.loc.gov/ead/ead.xsd</xsl:attribute>
					</xsl:when>
					<xsl:otherwise>
						<xsl:apply-templates select="@*"/>
					</xsl:otherwise>
				</xsl:choose>
				
				<!-\- apply remaining templates -\->
				<xsl:apply-templates select="*"/>
			</xsl:element>
		</xsl:result-document>-->		
		<xsl:element name="ead" namespace="urn:isbn:1-931666-22-9" xmlns:xlink="http://www.w3.org/1999/xlink">
			<xsl:namespace name="xlink">http://www.w3.org/1999/xlink</xsl:namespace>
			<xsl:choose>
				<xsl:when test="not(@xsi:noNamespaceSchemaLocation)">
					<xsl:namespace name="xsi">http://www.w3.org/2001/XMLSchema-instance</xsl:namespace>
					<xsl:attribute name="xsi:schemaLocation">urn:isbn:1-931666-22-9 http://www.loc.gov/ead/ead.xsd</xsl:attribute>
				</xsl:when>
				<xsl:otherwise>
					<xsl:apply-templates select="@*"/>
				</xsl:otherwise>
			</xsl:choose>
			
			<!-- apply remaining templates -->
			<xsl:apply-templates select="*"/>
		</xsl:element>
	</xsl:template>

	<xsl:template match="*[local-name()='eadheader']">
		<xsl:element name="eadheader" namespace="urn:isbn:1-931666-22-9">
			<xsl:apply-templates select="@*"/>
			<!-- handle eadid -->
			<!--<xsl:if test="not(child::*[local-name()='eadid'])">
				<xsl:element name="eadid" namespace="urn:isbn:1-931666-22-9">
					<xsl:attribute name="mainagencycode" select="concat('US-', $agencyCode)"/>
					<xsl:attribute name="url" select="base-uri()"/>
					<xsl:value-of select="$eadid"/>
				</xsl:element>
			</xsl:if>-->
			<xsl:apply-templates/>
		</xsl:element>
	</xsl:template>

	<!--<xsl:template match="*[local-name()='eadid']">
		<xsl:element name="eadid" namespace="urn:isbn:1-931666-22-9">
			<xsl:attribute name="mainagencycode" select="concat('US-', $agencyCode)"/>
			<xsl:attribute name="url" select="base-uri()"/>
			<xsl:value-of select="$eadid"/>
		</xsl:element>
	</xsl:template>-->
	
	<!-- remove unitdate from unittitle, move up a level -->
	<xsl:template match="*[local-name()='unittitle']">
		<xsl:element name="unittitle" namespace="urn:isbn:1-931666-22-9">
			<xsl:apply-templates select="@*|text()|node()[not(local-name() = 'unitdate')]"/>
		</xsl:element>
		
		
		<!-- unitdate is separated out as a sibling of unittitle and child of the did -->
		<xsl:if test="string(*[local-name()='unitdate'][1])">
			<xsl:apply-templates select="*[local-name()='unitdate']"/>
		</xsl:if>
	</xsl:template>
	
	<!-- use unnumbered components -->
	<xsl:template match="*[matches(local-name(), 'c[0-1]')]">		
		<xsl:element name="c" namespace="urn:isbn:1-931666-22-9">			
			<xsl:attribute name="id" select="if (string(@id)) then @id else generate-id()"/>			
			<xsl:apply-templates select="@*[not(name()='id')]|node()"/>
		</xsl:element>
	</xsl:template>
	
	<!-- flatten controlaccess -->
	<xsl:template match="*[local-name()='controlaccess']">
		<xsl:element name="controlaccess" namespace="urn:isbn:1-931666-22-9">
			<xsl:apply-templates select="@*"/>
			<xsl:for-each
				select="descendant::*[local-name()='corpname'] | descendant::*[local-name()='famname'] | descendant::*[local-name()='function'] | descendant::*[local-name()='genreform'] | descendant::*[local-name()='geogname'] | descendant::*[local-name()='name'] | descendant::*[local-name()='occupation'] | descendant::*[local-name()='persname'] | descendant::*[local-name()='subject'] | descendant::*[local-name()='title']">
				<xsl:apply-templates select="."/>
			</xsl:for-each>
		</xsl:element>
	</xsl:template>
	
	<!-- suppress invalid dates -->
	<xsl:template match="@normal">
		<xsl:choose>
			<xsl:when test="tokenize(., '/')[2]">
				<xsl:variable name="dates" select="tokenize(., '/')"/>
				<xsl:if test="my:validateDate($dates[1]) = true() and my:validateDate($dates[2]) = true()">
					<xsl:attribute name="normal" select="."/>
				</xsl:if>
			</xsl:when>
			<xsl:otherwise>
				<xsl:if test="my:validateDate(.) = true()">
					<xsl:attribute name="normal" select="."/>
				</xsl:if>				
			</xsl:otherwise>
		</xsl:choose>		
	</xsl:template>
	
	<!-- from the dtd2schema.xsl -->
	<xsl:template match="@*">
		<xsl:choose>
			<xsl:when test="normalize-space(.)= ''"/>

			<!-- UNCOMMENT the following line if mainagencycode can be verified to validate.  If it does not validate to the schema, it cannot be posted to eXist! -->
			<!--<xsl:when test="name(.) = 'mainagencycode'">
				<xsl:attribute name="{name(.)}">
					<xsl:value-of select="concat('US-', $agencyCode)"/>
				</xsl:attribute>
			</xsl:when>-->
			<!--<xsl:when test="name(.) = 'repositorycode'">
				<xsl:attribute name="{name(.)}">
					<xsl:value-of select="concat('US-', $agencyCode)"/>
				</xsl:attribute>
			</xsl:when>-->
			<xsl:when test="name() = 'countrycode'">
				<xsl:attribute name="{name()}">US</xsl:attribute>
			</xsl:when>
			<xsl:when test="name(.) = 'type' and parent::*/local-name()='container'">
				<xsl:attribute name="{name(.)}">
					<xsl:value-of select="lower-case(.)"/>
				</xsl:attribute>
			</xsl:when>
			<xsl:otherwise>
				<xsl:attribute name="{name(.)}">
					<xsl:value-of select="normalize-space(.)"/>
				</xsl:attribute>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template match="*">
		<xsl:element name="{name()}" namespace="urn:isbn:1-931666-22-9">
			<xsl:apply-templates select="*|@*|text()"/>
		</xsl:element>
	</xsl:template>

	<xsl:template match="text()">
		<xsl:value-of select="normalize-space(.)"/>
	</xsl:template>

	<!-- function for date validation -->
	<xsl:function name="my:validateDate" as="xs:boolean">
		<xsl:param name="date"/>
		
		<xsl:choose>			
			<xsl:when test="$date castable as xs:date or $date castable as xs:gYear or $date castable as xs:gYearMonth">
				<xsl:choose>
					<xsl:when test="number($date) and number($date) &gt; 2099">false</xsl:when>
					<xsl:otherwise>true</xsl:otherwise>
				</xsl:choose>
			</xsl:when>
			<xsl:otherwise>false</xsl:otherwise>
		</xsl:choose>
		
	</xsl:function>
</xsl:stylesheet>
