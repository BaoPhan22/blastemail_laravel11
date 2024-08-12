<?php

namespace App\Http\Controllers;

use App\Models\BlastEmail;
use App\Models\LastVisitedUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class BlastEmailController extends Controller
{
    public function index()
    {
        try {
            $emails = BlastEmail::select('email', DB::raw('count(*) as occurrences'))
                ->whereDate('created_at', (date('Y-m-d')))
                ->orderBy('occurrences', 'desc')
                ->groupBy('email')
                ->get();

            $emails_count = BlastEmail::get();
            $status = DB::table('control_flags')->where('name', 'crawling_active')->first();
            return view('welcome', ['emails' => $emails, 'emails_count' => $emails_count, 'status' => $status]);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('RequestException: ' . $e->getMessage());
            return redirect()->route('index')->with('error', 'An error occurred while fetching emails.');
        } catch (\Exception $e) {
            Log::error('Exception: ' . $e->getMessage());
            return redirect()->route('index')->with('error', 'An unexpected error occurred.');
        }
    }

    public function blastemail(Request $request)
    {
        set_time_limit(0); // Allow script to run indefinitely

        $initUrl = empty($request->url)
            ? (LastVisitedUrl::latest()->first()->url ?? 'https://singroll.com/web/')
            : $request->url;

        LastVisitedUrl::updateOrCreate(
            ['id' => 1],
            ['url' => $initUrl]
        );

        $collectedEmails = [];
        $visitedUrls = [];

        DB::table('control_flags')->where('name', 'crawling_active')->update(['active' => true]);

        $this->crawlPage($initUrl, $collectedEmails, $visitedUrls);

        DB::table('control_flags')->where('name', 'crawling_active')->update(['active' => false]);

        return redirect()->route('index')->with('status', 'Crawling completed.');
    }

    private function crawlPage($url, &$collectedEmails, &$visitedUrls, $retryCount = 3)
    {
        $skipExtensions = ['.jpg', '.png', '.gif', '.webp', 'mp'];
        if (!DB::table('control_flags')->where('name', 'crawling_active')->value('active')) {
            @Log::channel('blast_email')->info("Crawling stopped by user.");
            exit;
        }

        if (empty($url) || in_array($url, $visitedUrls)) {
            return;
        }

        $visitedUrls[] = $url;
        @Log::channel('blast_email')->info("Fetching: $url");

        $attempt = 0;
        $success = false;

        while ($attempt < $retryCount && !$success) {
            try {
                $attempt++;
                $response = Http::timeout(10)->get($url);

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
                            BlastEmail::updateOrCreate(['email' => $email], ['email' => $email]);
                            $filteredEmails[] = $email;
                        }
                    }

                    @Log::channel('blast_email')->info("Emails", ['emails' => $emailMatches[0]]);
                    @Log::channel('blast_email')->info("Stop Fetching: $url");

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