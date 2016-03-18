<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:ead="urn:isbn:1-931666-22-9" xmlns:xs="http://www.w3.org/2001/XMLSchema"
	version="2.0">
	<xsl:strip-space elements="*"/>
	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>
	
	<xsl:template match="@*|node()">
		<xsl:copy>
			<xsl:apply-templates select="@*|node()"/>
		</xsl:copy>
	</xsl:template>
	
	<xsl:template match="text()">
		<xsl:value-of select="normalize-space(.)"/>
	</xsl:template>
	
	<xsl:template match="ead:c">
		<xsl:element name="c" namespace="urn:isbn:1-931666-22-9">
			<xsl:apply-templates select="@*|*[not(local-name()='daogrp')]"/>
			<xsl:apply-templates select="ead:daogrp"/>
		</xsl:element>
	</xsl:template>
	
	<xsl:template match="ead:c/ead:daogrp">
		<xsl:element name="c" namespace="urn:isbn:1-931666-22-9">
			<xsl:attribute name="level">item</xsl:attribute>
			<xsl:attribute name="id" select="generate-id()"/>
			
			<xsl:variable name="desc" select="normalize-space(ead:daodesc/ead:p)"/>
			
			<xsl:element name="did" namespace="urn:isbn:1-931666-22-9">
				<xsl:element name="unittitle" namespace="urn:isbn:1-931666-22-9">
					<xsl:choose>
						<xsl:when test="contains($desc, '.')">
							<xsl:value-of select="substring-before($desc, '.')"/>
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="$desc"/>
						</xsl:otherwise>
					</xsl:choose>					
				</xsl:element>
				
				<!-- create unitdate and unitid if possible -->
				<xsl:analyze-string regex="^.*(\d{{4}}).*\(([0-9]{{2}}-[0-9]+)\).*" select="$desc">
					<xsl:matching-substring>
						<xsl:if test="regex-group(1)">
							<xsl:element name="unitdate" namespace="urn:isbn:1-931666-22-9">
								<xsl:if test="regex-group(1) castable as xs:gYear">
									<xsl:attribute name="normal" select="regex-group(1)"/>
								</xsl:if>
								<xsl:value-of select="regex-group(1)"/>
							</xsl:element>
						</xsl:if>
						<xsl:if test="regex-group(2)">
							<xsl:element name="unitid" namespace="urn:isbn:1-931666-22-9">
								<xsl:value-of select="regex-group(2)"/>
							</xsl:element>
						</xsl:if>
					</xsl:matching-substring>
				</xsl:analyze-string>
			</xsl:element>	
			
			<!-- insert blank controlaccess -->
			<xsl:element name="controlaccess" namespace="urn:isbn:1-931666-22-9"/>
			
			<!-- apply templates for daogrp -->
			<xsl:element name="daogrp" namespace="urn:isbn:1-931666-22-9">
				<xsl:apply-templates/>
			</xsl:element>
		</xsl:element>
	</xsl:template>
	
	<!-- eliminate controlaccess -->
	<xsl:template match="ead:c/ead:controlaccess"/>
	
</xsl:stylesheet>