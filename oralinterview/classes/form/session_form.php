<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');

class mod_oralinterview_session_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $templates = $this->_customdata['templates'] ?? [];
        $questionsetname = $this->_customdata['questionsetname'] ?? '';
        $candidates = $this->_customdata['candidates'] ?? [];
        $existing_committee = $this->_customdata['existing_committee'] ?? [];
        
        // Add hidden fields first
        $id = $this->_customdata['id'] ?? 0;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $id);

        $mform->addElement('hidden', 'sessionid');
        $mform->setType('sessionid', PARAM_INT);

        // Template selection removed in SPAC flow: the interview has a single question set for all candidates.
        $mform->addElement('hidden', 'templateid');
        $mform->setType('templateid', PARAM_INT);
        if (!empty($templates)) {
            $firstid = (int)array_key_first($templates);
            $mform->setDefault('templateid', $firstid);
        }
        $display = $questionsetname !== '' ? $questionsetname : get_string('interview_questions', 'oralinterview');
        $mform->addElement('static', 'questionset_display', get_string('interview_questions', 'oralinterview'), s($display));

        $mform->addElement('select', 'candidateid', get_string('session_candidate', 'oralinterview'), $candidates);
        $mform->addRule('candidateid', null, 'required', null, 'client');

        // Committee members - custom search interface
        $mform->addElement('html', '<div id="committee-search-container" class="mt-3 mb-4">');
        $mform->addElement('html', '<div class="form-label fw-semibold mb-2">' . get_string('session_committee', 'oralinterview') . '</div>');
        $mform->addElement('html', '<div class="position-relative" style="max-width: 520px;">');
        $mform->addElement('html', '<input type="text" id="committee-search-input" class="form-control" placeholder="' . s(get_string('committee_search_placeholder', 'oralinterview')) . '">');
        $mform->addElement('html', '<div id="committee-search-results" class="list-group shadow-sm" style="position: absolute; background: #fff; border: 1px solid #dee2e6; max-height: 220px; overflow-y: auto; z-index: 1000; display: none; width: 100%; margin-top: 6px; border-radius: .375rem;"></div>');
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '<div id="committee-selected-list" class="border rounded p-3 bg-light mt-3" style="min-height: 64px;">');
        
        // Show existing committee members
        if (!empty($existing_committee)) {
            foreach ($existing_committee as $member) {
                $mform->addElement('html', '<span class="committee-member-tag badge bg-primary me-1 mb-1" data-userid="' . $member['id'] . '" style="font-size: 0.95rem; padding: .5rem .6rem;">');
                $mform->addElement('html', htmlspecialchars($member['fullname']) . ' (' . htmlspecialchars($member['username']) . ')');
                $mform->addElement('html', ' <span class="remove-committee ms-2" style="cursor:pointer;">×</span>');
                $mform->addElement('html', '</span>');
            }
        }
        
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '<div id="committee-debug" class="text-muted small mt-2">' . s(get_string('committee_selected', 'oralinterview', '0')) . '</div>');
        $mform->addElement('html', '</div>');
        
        // Hidden field to store selected user IDs
        $mform->addElement('hidden', 'committee');
        $mform->setType('committee', PARAM_TEXT); // Changed to TEXT to accept comma-separated string
        
        // Add JavaScript for search functionality
        $searchurl = new moodle_url('/mod/oralinterview/ajax_search_users.php');
        $existing_committee_json = json_encode($existing_committee);
        $i18n = json_encode([
            'selected' => get_string('committee_selected', 'oralinterview'),
            'none' => get_string('committee_none', 'oralinterview'),
            'searching' => get_string('committee_searching', 'oralinterview'),
            'noresults' => get_string('committee_no_users', 'oralinterview'),
            'searcherror' => get_string('committee_search_error', 'oralinterview'),
        ]);
        $js = "
        <script>
        (function() {
            var searchInput = document.getElementById('committee-search-input');
            var resultsDiv = document.getElementById('committee-search-results');
            var selectedList = document.getElementById('committee-selected-list');
            var selectedUsers = new Set();
            var i18n = " . $i18n . ";
            
            // Load existing committee members from form data
            var formData = " . $existing_committee_json . ";
            if (formData && formData.length > 0) {
                formData.forEach(function(member) {
                    selectedUsers.add(member.id.toString());
                });
            }
            
            // Also load from any existing tags in DOM
            document.querySelectorAll('.committee-member-tag').forEach(function(tag) {
                var userid = tag.getAttribute('data-userid');
                selectedUsers.add(userid);
            });
            
            // Update hidden field
            function updateHiddenField() {
                // Try multiple selectors - Moodle forms might use different naming
                var hiddenInput = document.querySelector('input[name=\"committee\"]') ||
                                  document.querySelector('input[name=\"mform_isexpanded_id_committee\"]') ||
                                  document.querySelector('input[type=\"hidden\"][name*=\"committee\"]');
                
                var value = Array.from(selectedUsers).join(',');
                
                if (hiddenInput) {
                    hiddenInput.value = value;
                    console.log('Updated hidden field:', hiddenInput.name, '=', value);
                } else {
                    console.warn('Could not find hidden committee input field, creating one...');
                    // Create it if it doesn't exist
                    var form = document.querySelector('form');
                    if (form) {
                        // Remove any existing one first
                        var existing = form.querySelector('input[name=\"committee\"]');
                        if (existing) {
                            existing.remove();
                        }
                        
                        var newInput = document.createElement('input');
                        newInput.type = 'hidden';
                        newInput.name = 'committee';
                        newInput.id = 'committee-hidden-field';
                        newInput.value = value;
                        form.appendChild(newInput);
                        console.log('Created hidden field with value:', newInput.value);
                    } else {
                        console.error('ERROR: Could not find form element!');
                    }
                }
                
                // Also update a visible debug div if it exists
                var debugDiv = document.getElementById('committee-debug');
                if (debugDiv) {
                    var count = value ? value.split(',').filter(Boolean).length : 0;
                    if (count === 0) {
                        debugDiv.textContent = i18n.selected.replace('{$a}', i18n.none);
                    } else {
                        debugDiv.textContent = i18n.selected.replace('{$a}', String(count));
                    }
                }
            }
            
            // Remove committee member
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-committee')) {
                    var tag = e.target.closest('.committee-member-tag');
                    var userid = tag.getAttribute('data-userid');
                    selectedUsers.delete(userid);
                    tag.remove();
                    updateHiddenField();
                }
            });
            
            // Search function
            function performSearch() {
                var query = searchInput.value.trim();
                
                if (query.length < 2) {
                    resultsDiv.style.display = 'none';
                    resultsDiv.innerHTML = '';
                    return;
                }
                
                // Show loading
                resultsDiv.innerHTML = '<div class=\"list-group-item\">' + i18n.searching + '</div>';
                resultsDiv.style.display = 'block';
                
                fetch('" . $searchurl->out(false) . "?q=' + encodeURIComponent(query) + '&id=' + (document.querySelector('input[name=\"id\"]') ? document.querySelector('input[name=\"id\"]').value : ''))
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(err => {
                                throw new Error(err.error || 'Network response was not ok: ' + response.status);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Check if response has error
                        if (data.error) {
                            throw new Error(data.error);
                        }
                        resultsDiv.innerHTML = '';
                        
                        if (data.length === 0) {
                            resultsDiv.innerHTML = '<div class=\"list-group-item text-muted\">' + i18n.noresults + '</div>';
                        } else {
                            data.forEach(function(user) {
                                if (selectedUsers.has(user.id.toString())) {
                                    return; // Skip already selected
                                }
                                var div = document.createElement('div');
                                div.className = 'list-group-item list-group-item-action';
                                div.style.cursor = 'pointer';
                                div.innerHTML = '<div class=\"fw-semibold\">' + user.display + '</div><div class=\"small text-muted\">' + user.email + '</div>';
                                div.addEventListener('mouseenter', function() {
                                    this.style.background = '#f8f9fa';
                                });
                                div.addEventListener('mouseleave', function() {
                                    this.style.background = 'white';
                                });
                                div.addEventListener('click', function() {
                                    addCommitteeMember(user);
                                    searchInput.value = '';
                                    resultsDiv.style.display = 'none';
                                });
                                resultsDiv.appendChild(div);
                            });
                        }
                        resultsDiv.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        var errorMsg = error.message || i18n.searcherror;
                        resultsDiv.innerHTML = '<div class=\"list-group-item text-danger\">' + errorMsg + '</div>';
                        resultsDiv.style.display = 'block';
                    });
            }
            
            // Search on Enter key
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performSearch();
                }
            });
            
            // Search on input (with debounce)
            var searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                var query = this.value.trim();
                
                if (query.length < 2) {
                    resultsDiv.style.display = 'none';
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    performSearch();
                }, 500);
            });
            
            // Hide results when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                    resultsDiv.style.display = 'none';
                }
            });
            
            // Add committee member
            function addCommitteeMember(user) {
                if (selectedUsers.has(user.id.toString())) {
                    return; // Already added
                }
                
                selectedUsers.add(user.id.toString());
                
                var tag = document.createElement('div');
                tag.className = 'committee-member-tag';
                tag.setAttribute('data-userid', user.id);
                tag.className = 'committee-member-tag badge bg-primary me-1 mb-1';
                tag.style.cssText = 'font-size: 0.95rem; padding: .5rem .6rem;';
                tag.innerHTML = user.display + ' <span class=\"remove-committee ms-2\" style=\"cursor:pointer;\">×</span>';
                
                selectedList.appendChild(tag);
                updateHiddenField();
                
                // Debug
                console.log('Added committee member:', user.id, 'Total:', selectedUsers.size);
            }
            
            // Initialize hidden field
            updateHiddenField();
            
            // Ensure hidden field is updated before form submission
            var formElement = document.querySelector('form');
            if (formElement) {
                formElement.addEventListener('submit', function(e) {
                    updateHiddenField();
                    var hiddenInput = document.querySelector('input[name=\"committee\"]');
                    if (hiddenInput) {
                        console.log('Form submitting with committee:', hiddenInput.value);
                        if (!hiddenInput.value || hiddenInput.value.trim() === '') {
                            console.warn('WARNING: No committee members in hidden field!');
                        }
                    }
                }, false);
            }
            
            // Also update before form submit to ensure it's saved
            var form = document.querySelector('form[id*=\"mform\"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    updateHiddenField();
                    // Debug: log the value
                    var hiddenInput = document.querySelector('input[name=\"committee\"]');
                    if (hiddenInput) {
                        console.log('Committee members being saved:', hiddenInput.value);
                        if (!hiddenInput.value || hiddenInput.value.trim() === '') {
                            console.warn('Warning: No committee members selected!');
                        }
                    }
                });
            }
        })();
        </script>
        ";
        $mform->addElement('html', $js);

        $mform->addElement('date_time_selector', 'interviewdate', get_string('session_interviewdate', 'oralinterview'));
        $mform->setDefault('interviewdate', time());

        $mform->addElement('date_time_selector', 'deadline', get_string('session_deadline', 'oralinterview'), ['optional' => false]);
        $mform->setDefault('deadline', time());

        $mform->addElement('advcheckbox', 'requireall', get_string('session_requireall', 'oralinterview'));
        $mform->setDefault('requireall', 1);

        $statusoptions = [
            'draft' => get_string('session_status_draft', 'oralinterview'),
            'active' => get_string('session_status_active', 'oralinterview')
        ];
        $mform->addElement('select', 'status', get_string('session_status', 'oralinterview'), $statusoptions);
        $mform->setDefault('status', 'draft');

        $mform->addElement('static', 'session_instruction', '', get_string('session_instruction', 'oralinterview'));

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
