<?php

namespace App\Http\Controllers;

use App\Models\BlastEmail;
use App\Models\LastVisitedUrl;
use App\Models\SkipExtension;
use App\Models\SkipSite;
use App\Models\VisitedUrls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class BlastEmailController extends Controller
{
    protected $skip_sites;
    protected $skip_extensions;
    protected $visited_urls;

    public function __construct()
    {
        $this->skip_sites = SkipSite::pluck('url')->toArray();
        $this->skip_extensions = SkipExtension::pluck('extension')->toArray();
        $this->visited_urls = VisitedUrls::pluck('url')->toArray();
    }

    public function index()
    {
        try {
            $emails = BlastEmail::select('email', 'id')
                ->where('status', 1)
                ->whereDate('created_at', (date('Y-m-d')))
                ->orderBy('id', 'desc')
                ->get();

            $emails_count = BlastEmail::get();
            $skip_sites = $this->skip_sites;
            $skip_extensions = $this->skip_extensions;
            $status = DB::table('control_flags')->where('name', 'crawling_active')->first();
            return view('welcome', ['emails' => $emails, 'emails_count' => $emails_count, 'status' => $status, 'skip_sites' => $skip_sites, 'skip_extensions' => $skip_extensions]);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('RequestException: ' . $e->getMessage());
            return redirect()->route('index')->with('error', 'An error occurred while fetching emails.');
        } catch (\Exception $e) {
            Log::error('Exception: ' . $e->getMessage());
            return redirect()->route('index')->with('error', 'An unexpected error occurred.');
        }
    }
    public function hideemail(Request $request, $id)
    {
        BlastEmail::where('id', $id)->update(['status' => 0]);
        return redirect()->route('index');
    }

    public function skip_site(Request $request)
    {
        SkipSite::firstOrCreate(['url' => $request->url]);
        return redirect()->route('index');
    }

    public function skip_extension(Request $request)
    {
        SkipExtension::firstOrCreate(['extension' => $request->extension]);
        return redirect()->route('index');
    }

    public function blastemail(Request $request)
    {
        set_time_limit(0); // Allow script to run indefinitely

        $initUrl = empty($request->url)
            ? (LastVisitedUrl::where('id', 1)->first()->url ?? 'https://singroll.com/web/')
            : $request->url;

        LastVisitedUrl::updateOrCreate(
            ['id' => 1],
            ['url' => $initUrl]
        );

        $collectedEmails = [];
        $visitedUrls = $this->visited_urls;

        DB::table('control_flags')->where('name', 'crawling_active')->update(['active' => true]);

        $this->crawlPage($initUrl, $collectedEmails, $visitedUrls);

        DB::table('control_flags')->where('name', 'crawling_active')->update(['active' => false]);

        return redirect()->route('index')->with('status', 'Crawling completed.');
    }

    private function crawlPage($url, &$collectedEmails, &$visitedUrls, $retryCount = 2)
    {
        $skipExtensions = $this->skip_extensions;

        $skip_sites = $this->skip_sites;

        if (!DB::table('control_flags')->where('name', 'crawling_active')->value('active')) {
            @Log::channel('blast_email')->info("Crawling stopped by user.");
            exit;
        }

        if (empty($url) || in_array($url, $visitedUrls)) {
            return;
        }
        foreach ($skipExtensions as $extension) {
            if (substr($url, -strlen($extension)) === $extension) {
                return;
            }
        }
        // Skip if URL matches any in the skipSites list
        foreach ($skip_sites as $site) {
            if (strpos($url, $site) === 0) {
                // Log::channel('blast_email')->info("Skipping URL: {$url} as it matches skip_sites criteria.");
                return;
            }
        }

        $visitedUrls[] = $url;
        VisitedUrls::firstOrCreate(['url' => $url]);
        // @Log::channel('blast_email')->info("Fetching: $url");

        $attempt = 0;
        $success = false;

        while ($attempt < $retryCount && !$success) {
            try {
                $attempt++;
                $response = Http::timeout(5)->get($url);

                if ($response->successful()) {
                    $content = $response->body();

                    $emailPattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';

                    preg_match_all($emailPattern, $content, $emailMatches);

                    $filteredEmails = [];

                    foreach ($emailMatches[0] as $email) {
                        $skip = false;
                        foreach ($skipExtensions as $extension) {
                            if (substr($email, -strlen($extension)) === $extension) {
                                $skip = true;
                                break;
                            }
                        }

                        if (!$skip) {
                            $emailRecord = BlastEmail::firstOrCreate(['email' => $email]);
                            $filteredEmails[] = $emailRecord->email;
                        }
                    }

                    @Log::channel('blast_email')->info("Emails", ['emails' => $filteredEmails, 'url' => $url]);

                    // @Log::channel('blast_email')->info("Stop Fetching: $url");

                    $linkPattern = '/<a\s+href=["\']([^"\']+)["\']/i';
                    preg_match_all($linkPattern, $content, $linkMatches);

                    foreach ($linkMatches[1] as $foundLink) {
                        if (empty($foundLink) || $foundLink[0] === '#') {
                            continue;
                        }

                        $parsedUrl = parse_url($url);
                        if (!preg_match('/^https?:\/\//', $foundLink)) {
                            $foundLink = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/' . ltrim($foundLink, '/');
                        }

                        LastVisitedUrl::updateOrCreate(
                            ['id' => 1],
                            ['url' => $foundLink]
                        );

                        $this->crawlPage($foundLink, $collectedEmails, $visitedUrls);
                    }

                    $success = true;
                    @Log::channel('blast_email')->info("Stop Fetching Process");
                } else {
                    @Log::channel('blast_email')->info("Failed to retrieve the page content at $url with status code: " . $response->status());
                }
            } catch (\Illuminate\Http\Client\RequestException $e) {
                @Log::error("Attempt $attempt: Failed to retrieve the page content at $url. Error: " . $e->getMessage());
                if ($attempt >= $retryCount) {
                    @Log::channel('blast_email')->info("Max retry attempts reached for $url. Skipping...");
                } else {
                    @Log::channel('blast_email')->info("Retrying $url... Attempt $attempt of $retryCount");
                }
            } catch (\Exception $e) {
                @Log::error("Attempt $attempt: Failed to retrieve the page content at $url. Error: " . $e->getMessage());
                if ($attempt >= $retryCount) {
                    @Log::channel('blast_email')->info("Max retry attempts reached for $url. Skipping...");
                } else {
                    @Log::channel('blast_email')->info("Retrying $url... Attempt $attempt of $retryCount");
                }
            }
        }
    }
}
