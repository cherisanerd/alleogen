import { createClientFromRequest } from 'npm:@base44/sdk@0.8.6';
import { DOMParser } from 'npm:linkedom';

async function fetchWithTimeout(url, timeoutMs) {
  const controller = new AbortController();
  const t = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(url, {
      signal: controller.signal,
      headers: { 'User-Agent': 'Mozilla/5.0 (compatible; AEOBot/1.0; +https://aeofilegenerator.com)' }
    });
    clearTimeout(t);
    if (!response.ok) return { ok: false, status: response.status, text: '' };
    const text = await response.text();
    return { ok: true, status: response.status, text };
  } catch (error) {
    clearTimeout(t);
    return { ok: false, status: 0, text: '', error: error?.message || 'fetch failed' };
  }
}

Deno.serve(async (req) => {
  try {
    const base44 = createClientFromRequest(req);
    const user = await base44.auth.me();

    if (!user) {
      return Response.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const { website_url, user_id } = await req.json();

    // Create analysis record
    const analysis = await base44.asServiceRole.entities.Analysis.create({
      user_id: user_id,
      url: website_url,
      status: 'analyzing'
    });

    try {
      // Fetch website HTML with timeout
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 30000);
      
      const response = await fetch(website_url, {
        signal: controller.signal,
        headers: {
          'User-Agent': 'Mozilla/5.0 (compatible; AEOBot/1.0; +https://aeofilegenerator.com)'
        }
      });
      clearTimeout(timeout);

      const html = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');

      // Extract page title (full, for use in descriptive fields)
      let pageTitle = '';
      const titleEl = doc.querySelector('title');
      if (titleEl) {
        pageTitle = titleEl.textContent.trim();
      }

      // Extract tagline (h1 or og:description)
      let tagline = '';
      const h1El = doc.querySelector('h1');
      if (h1El) {
        tagline = h1El.textContent.trim();
      }
      const ogDesc = doc.querySelector('meta[property="og:description"]');
      if (!tagline && ogDesc) {
        tagline = ogDesc.getAttribute('content') || '';
      }

      // Extract business name separately:
      // 1. Try og:site_name (most reliable)
      // 2. Try schema.org Organization/LocalBusiness name
      // 3. Try the LAST segment of the title after | or -
      // 4. Fall back to domain name
      let businessName = '';
      const ogSiteName = doc.querySelector('meta[property="og:site_name"]');
      if (ogSiteName) {
        businessName = ogSiteName.getAttribute('content') || '';
      }
      if (!businessName) {
        const schemaEl = doc.querySelector('[itemtype*="Organization"] [itemprop="name"], [itemtype*="LocalBusiness"] [itemprop="name"]');
        if (schemaEl) {
          businessName = schemaEl.textContent.trim();
        }
      }
      if (!businessName && pageTitle) {
        // Try last segment after | or - as it's typically the brand name
        const pipeIdx = pageTitle.lastIndexOf('|');
        const dashIdx = pageTitle.lastIndexOf(' - ');
        if (pipeIdx > 0) {
          businessName = pageTitle.substring(pipeIdx + 1).trim();
        } else if (dashIdx > 0) {
          businessName = pageTitle.substring(dashIdx + 3).trim();
        } else {
          businessName = pageTitle.split('|')[0].split('-')[0].trim();
        }
      }
      if (!businessName) {
        // Fall back to domain name
        try {
          const domain = new URL(website_url).hostname.replace('www.', '');
          businessName = domain.split('.')[0].replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        } catch {}
      }

      // Extract logo URL (og:image or apple-touch-icon or schema logo)
      let logoUrl = '';
      const ogImage = doc.querySelector('meta[property="og:image"]');
      if (ogImage) logoUrl = ogImage.getAttribute('content') || '';
      if (!logoUrl) {
        const schemaLogo = doc.querySelector('[itemprop="logo"]');
        if (schemaLogo) logoUrl = schemaLogo.getAttribute('content') || schemaLogo.getAttribute('src') || '';
      }

      // Extract meta description
      let metaDescription = '';
      const metaDesc = doc.querySelector('meta[name="description"]');
      if (metaDesc) {
        metaDescription = metaDesc.getAttribute('content') || '';
      }

      // Extract founder/owner name and role
      let founderName = '';
      let founderRole = '';
      const personEls = doc.querySelectorAll('[itemtype*="Person"]');
      personEls.forEach(el => {
        const jobTitleEl = el.querySelector('[itemprop="jobTitle"]');
        const nameEl = el.querySelector('[itemprop="name"]');
        if (jobTitleEl && nameEl && !founderName) {
          const title = jobTitleEl.textContent.toLowerCase();
          if (/founder|owner|ceo|director|president|creator/.test(title)) {
            founderName = nameEl.textContent.trim();
            founderRole = jobTitleEl.textContent.trim();
          }
        }
      });
      // Fallback: look for common founder byline patterns in body text
      if (!founderName) {
        const bodyText = doc.querySelector('body')?.textContent || '';
        const founderMatch = bodyText.match(/(?:founded by|created by|by)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2})/);
        if (founderMatch) {
          founderName = founderMatch[1].trim();
          founderRole = 'Founder';
        }
      }

      // Extract named products and programs (schema Product + prominent headings near prices)
      const namedProducts = [];
      doc.querySelectorAll('[itemtype*="Product"] [itemprop="name"]').forEach(el => {
        const name = el.textContent.trim();
        if (name && name.length < 80 && !namedProducts.includes(name)) namedProducts.push(name);
      });

      // Extract proprietary frameworks/methodologies (words near ™ or ® symbols)
      const frameworks = [];
      const tmMatches = html.match(/([A-Z][a-zA-Z\u00C0-\u024F\s]{1,40}?)[™®]/g);
      if (tmMatches) {
        tmMatches.forEach(m => {
          const name = m.replace(/[™®]/g, '').trim();
          if (name && name.length > 2 && !frameworks.includes(name)) frameworks.push(name);
        });
      }

      // Extract target audience description
      let targetAudience = '';
      const fullBodyText = doc.querySelector('body')?.textContent || '';
      const audiencePatterns = [
        /designed for ([^.]{5,80})/i,
        /built for ([^.]{5,80})/i,
        /perfect for ([^.]{5,80})/i,
        /made for ([^.]{5,80})/i,
        /helping ([^.]{5,80}) (?:to|grow|achieve|build|scale)/i,
        /for ([^.]{5,60}) who (?:want|need|are|have)/i
      ];
      for (const pattern of audiencePatterns) {
        const match = fullBodyText.match(pattern);
        if (match) {
          targetAudience = match[1].trim().replace(/\s+/g, ' ').substring(0, 120);
          break;
        }
      }

      // Extract social proof signals (community size, counts)
      const socialProofSignals = [];
      const proofPatterns = [
        /(\d[\d,]+\+?)\s+(members|students|customers|clients|users|followers|subscribers|downloads|reviews|businesses)/gi,
        /(?:trusted by|joined by|used by)\s+(\d[\d,]+\+?\s+\w+)/gi,
        /(\d[\d,]+\+?)\s+(?:5[\-\s]?star|happy|satisfied)/gi
      ];
      proofPatterns.forEach(pattern => {
        let match;
        while ((match = pattern.exec(fullBodyText)) !== null) {
          const signal = match[0].trim().replace(/\s+/g, ' ');
          if (!socialProofSignals.includes(signal) && socialProofSignals.length < 5) {
            socialProofSignals.push(signal);
          }
        }
      });

      // Extract FAQ pairs (Q+A from schema FAQPage or common DOM patterns)
      const faqItems = [];
      // Schema.org FAQPage
      doc.querySelectorAll('[itemtype*="Question"]').forEach(el => {
        const q = el.querySelector('[itemprop="name"]')?.textContent?.trim();
        const a = el.querySelector('[itemprop="acceptedAnswer"] [itemprop="text"]')?.textContent?.trim();
        if (q && a && faqItems.length < 10) faqItems.push({ q, a: a.substring(0, 400) });
      });
      // Fallback: look for elements with FAQ-like class names or headings followed by paragraphs
      if (faqItems.length === 0) {
        const faqSections = doc.querySelectorAll('[class*="faq"] dt, [class*="faq"] h3, [id*="faq"] h3, [class*="accordion"] [class*="title"], [class*="accordion"] summary');
        faqSections.forEach(el => {
          const q = el.textContent.trim();
          // Try to get the next sibling answer
          const next = el.nextElementSibling;
          const a = next ? next.textContent.trim().substring(0, 400) : '';
          if (q && q.length > 10 && q.length < 200 && a && faqItems.length < 10) {
            faqItems.push({ q, a });
          }
        });
      }

      // Extract blog post titles and excerpts
      const blogPosts = [];
      // Look for article elements, blog card patterns
      const articleEls = doc.querySelectorAll('article, [class*="blog-post"], [class*="blog-card"], [class*="post-card"]');
      articleEls.forEach(article => {
        const titleEl = article.querySelector('h2, h3, [class*="title"], [class*="heading"]');
        const excerptEl = article.querySelector('p, [class*="excerpt"], [class*="summary"], [class*="description"]');
        const title = titleEl?.textContent?.trim();
        const excerpt = excerptEl?.textContent?.trim()?.substring(0, 200);
        if (title && title.length > 5 && title.length < 150 && blogPosts.length < 8) {
          blogPosts.push({ title, excerpt: excerpt || '' });
        }
      });

      // Extract named products with descriptions (schema Product or pricing cards)
      const namedProductsWithDesc = [];
      doc.querySelectorAll('[itemtype*="Product"]').forEach(el => {
        const name = el.querySelector('[itemprop="name"]')?.textContent?.trim();
        const desc = el.querySelector('[itemprop="description"]')?.textContent?.trim()?.substring(0, 300);
        if (name && name.length < 80 && namedProductsWithDesc.length < 8) {
          namedProductsWithDesc.push({ name, description: desc || '' });
        }
      });

      // Extract industry keywords from meta description and content
      const industryKeywords = [];
      if (metaDescription) {
        const words = metaDescription.toLowerCase().split(/\s+/);
        const commonWords = ['the', 'and', 'for', 'with', 'your', 'our', 'we', 'are', 'is'];
        words.forEach(word => {
          if (word.length > 4 && !commonWords.includes(word) && industryKeywords.length < 7) {
            industryKeywords.push(word);
          }
        });
      }

      // Extract contact email
      let contactEmail = '';
      const mailtoLinks = doc.querySelectorAll('a[href^="mailto:"]');
      if (mailtoLinks.length > 0) {
        contactEmail = mailtoLinks[0].getAttribute('href').replace('mailto:', '').split('?')[0];
      }

      // Extract phone number
      let phoneNumber = '';
      const telLinks = doc.querySelectorAll('a[href^="tel:"]');
      if (telLinks.length > 0) {
        phoneNumber = telLinks[0].getAttribute('href').replace('tel:', '').trim();
      }

      // Extract address (look for common patterns)
      let address = '';
      const addressEl = doc.querySelector('[itemtype*="PostalAddress"]') || 
                        doc.querySelector('.address') || 
                        doc.querySelector('[class*="address"]');
      if (addressEl) {
        address = addressEl.textContent.trim().replace(/\s+/g, ' ');
      }

      // Detect platform
      let platform = 'Custom/Other';
      if (html.includes('wp-content') || html.includes('wordpress')) {
        platform = 'WordPress';
      } else if (html.includes('shopify') || html.includes('cdn.shopify.com')) {
        platform = 'Shopify';
      } else if (html.includes('wix.com') || html.includes('static.wixstatic.com')) {
        platform = 'Wix';
      } else if (html.includes('squarespace')) {
        platform = 'Squarespace';
      } else if (html.includes('webflow.io') || html.includes('webflow.com') || html.includes('data-wf-')) {
        platform = 'Webflow';
      } else if (html.includes('framer.com') || html.includes('framerusercontent.com')) {
        platform = 'Framer';
      } else if (html.includes('kajabi') || html.includes('kajabi.com')) {
        platform = 'Kajabi';
      } else if (html.includes('ghost.io') || html.includes('ghost-theme')) {
        platform = 'Ghost';
      }

      // Extract social links
      const socialLinks = [];
      const socialDomains = ['facebook.com', 'twitter.com', 'linkedin.com', 'instagram.com', 'youtube.com'];
      const allLinks = doc.querySelectorAll('a[href]');
      
      allLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && socialDomains.some(domain => href.includes(domain))) {
          if (!socialLinks.includes(href) && socialLinks.length < 5) {
            socialLinks.push(href);
          }
        }
      });

      // Detect site structure
      const navLinks = doc.querySelectorAll('nav a, .menu a, [class*="menu"] a, [class*="nav"] a');
      const linkTexts = Array.from(navLinks).map(a => a.textContent.toLowerCase());
      const allText = html.toLowerCase();

      const siteStructure = {
        hasProducts: allText.includes('product') || allText.includes('shop') || allText.includes('store'),
        hasBlog: linkTexts.some(t => t.includes('blog')) || allText.includes('blog'),
        hasServices: linkTexts.some(t => t.includes('service')) || allText.includes('services'),
        hasTeam: linkTexts.some(t => t.includes('team') || t.includes('about')) || allText.includes('our team'),
        hasAbout: linkTexts.some(t => t.includes('about')),
        hasContact: linkTexts.some(t => t.includes('contact')) || contactEmail !== ''
      };

      // Estimate page count
      const uniqueLinks = new Set();
      allLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.startsWith('/') && !href.includes('#')) {
          uniqueLinks.add(href);
        }
      });
      const pageCount = Math.max(uniqueLinks.size, 5);

      // === Phase 2 scraper additions ===

      // Existing JSON-LD schema types on the page
      const existingSchemaTypes = [];
      const schemaRawBlocks = [];
      doc.querySelectorAll('script[type="application/ld+json"]').forEach(el => {
        const raw = el.textContent?.trim();
        if (!raw) return;
        if (schemaRawBlocks.length < 5) schemaRawBlocks.push(raw.slice(0, 2000));
        try {
          const parsed = JSON.parse(raw);
          const items = Array.isArray(parsed) ? parsed : (parsed['@graph'] || [parsed]);
          items.forEach(item => {
            const t = item?.['@type'];
            if (!t) return;
            const types = Array.isArray(t) ? t : [t];
            types.forEach(tt => { if (tt && !existingSchemaTypes.includes(tt)) existingSchemaTypes.push(tt); });
          });
        } catch { /* malformed JSON-LD — ignore */ }
      });
      // Microdata types
      doc.querySelectorAll('[itemtype]').forEach(el => {
        const t = el.getAttribute('itemtype') || '';
        const match = t.match(/schema\.org\/(\w+)/);
        if (match && !existingSchemaTypes.includes(match[1])) existingSchemaTypes.push(match[1]);
      });
      const existingSchema = { types: existingSchemaTypes, raw: schemaRawBlocks };

      // OG + Twitter Card tag presence
      const ogTags = {
        title: !!doc.querySelector('meta[property="og:title"]'),
        description: !!doc.querySelector('meta[property="og:description"]'),
        image: !!doc.querySelector('meta[property="og:image"]'),
        type: !!doc.querySelector('meta[property="og:type"]'),
        twitterCard: !!doc.querySelector('meta[name="twitter:card"]')
      };

      // Canonical URL detection
      const canonicalEl = doc.querySelector('link[rel="canonical"]');
      const canonical = {
        present: !!canonicalEl,
        url: canonicalEl?.getAttribute('href') || ''
      };

      // Internal link inventory (up to 50 unique URLs with anchor text)
      const internalLinks = [];
      const seenInternal = new Set();
      let siteOrigin = '';
      try { siteOrigin = new URL(website_url).origin; } catch {}
      allLinks.forEach(link => {
        if (internalLinks.length >= 50) return;
        const href = link.getAttribute('href');
        if (!href) return;
        if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) return;
        let absolute = '';
        try {
          absolute = new URL(href, website_url).toString().split('#')[0];
        } catch { return; }
        if (siteOrigin && !absolute.startsWith(siteOrigin)) return;
        if (seenInternal.has(absolute)) return;
        seenInternal.add(absolute);
        const anchorText = (link.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 120);
        internalLinks.push({ url: absolute, anchorText });
      });

      // Heading structure (H1/H2/H3)
      const headingStructure = [];
      doc.querySelectorAll('h1, h2, h3').forEach(h => {
        if (headingStructure.length >= 50) return;
        const level = Number(h.tagName?.[1]) || 0;
        const text = (h.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 200);
        if (text) headingStructure.push({ level, text });
      });

      // Fetch existing robots.txt + sitemap.xml (non-blocking, 5s each)
      const [robotsResult, sitemapResult] = await Promise.allSettled([
        fetchWithTimeout(`${siteOrigin}/robots.txt`, 5000),
        fetchWithTimeout(`${siteOrigin}/sitemap.xml`, 5000)
      ]);
      const existingRobotsTxt = robotsResult.status === 'fulfilled' && robotsResult.value.ok
        ? { present: true, content: robotsResult.value.text.slice(0, 4000) }
        : { present: false, content: '' };
      let existingSitemap = { present: false, urlCount: 0 };
      if (sitemapResult.status === 'fulfilled' && sitemapResult.value.ok) {
        const matches = sitemapResult.value.text.match(/<loc>/g);
        existingSitemap = { present: true, urlCount: matches ? matches.length : 0 };
      }

      const extractedData = {
        businessName,
        pageTitle,
        tagline,
        metaDescription,
        industryKeywords,
        contactEmail,
        phoneNumber,
        address,
        platform,
        socialLinks,
        siteStructure,
        pageCount,
        logoUrl,
        founderName,
        founderRole,
        namedProducts,
        namedProductsWithDesc,
        frameworks,
        targetAudience,
        socialProofSignals,
        faqItems,
        blogPosts,
        existingSchema,
        ogTags,
        canonical,
        internalLinks,
        headingStructure,
        existingRobotsTxt,
        existingSitemap
      };

      // Update analysis with results
      await base44.asServiceRole.entities.Analysis.update(analysis.id, {
        status: 'completed',
        platform: platform,
        page_count: pageCount,
        extracted_data: extractedData,
        site_structure: siteStructure,
        completed_at: new Date().toISOString()
      });

      // Create generation record
      const generation = await base44.asServiceRole.entities.Generation.create({
        user_id: user_id,
        analysis_id: analysis.id,
        website_url: website_url,
        business_name: businessName,
        package_tier: user.plan_type === 'complete' ? 'complete' : 'basic',
        status: 'questionnaire_incomplete'
      });

      return Response.json({
        success: true,
        analysis_id: analysis.id,
        generation_id: generation.id,
        extracted_data: extractedData
      });

    } catch (fetchError) {
      console.error('Error fetching website:', fetchError);
      
      // Update analysis status to failed
      await base44.asServiceRole.entities.Analysis.update(analysis.id, {
        status: 'failed',
        error_message: 'Could not fetch website: ' + fetchError.message,
        completed_at: new Date().toISOString()
      });

      let errorMessage = 'Could not analyze website. Please check the URL and try again.';
      if (fetchError.name === 'AbortError') {
        errorMessage = 'Website analysis timed out (over 30 seconds). The website might be too slow or blocking requests.';
      } else if (fetchError.message && fetchError.message.includes('fetch')) {
        errorMessage = 'Could not connect to the website. Please check the URL and ensure it is publicly accessible.';
      }

      return Response.json({ 
        error: errorMessage 
      }, { status: 500 });
    }

  } catch (error) {
    console.error('Website analysis error:', error);
    return Response.json({ error: error.message || 'Analysis failed' }, { status: 500 });
  }
});