document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;

    let lastScrollY = window.scrollY;

    window.addEventListener('scroll', () => {
        // Only trigger hide effect if user has scrolled down past 100px 
        // to prevent erratic jumping at the very top of the page.
        if (window.scrollY > lastScrollY && window.scrollY > 100) {
            navbar.style.transform = 'translateY(-150%)';
        } else {
            navbar.style.transform = 'translateY(0)';
        }
        lastScrollY = window.scrollY;
    });

    // User Navigation Avatar & Logout
    const avatars = document.querySelectorAll('.dev-avatar');
    avatars.forEach(avatar => {
        avatar.addEventListener('click', () => {
            if (confirm("Are you sure you want to log out?")) {
                fetch('api/logout.php')
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            localStorage.removeItem('isLoggedIn');
                            localStorage.removeItem('userRole');
                            localStorage.removeItem('currentUser');
                            window.location.href = res.redirect || 'index.html';
                        }
                    });
            }
        });

        // Try to set initials if logged in
        const name = localStorage.getItem('currentUser');
        if (name && avatar.textContent.length <= 2) {
            avatar.textContent = name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
        }
    });

    // Chat Widget Toggle Logic
    const chatToggleBtn = document.getElementById('chat-toggle-btn');
    const chatCloseBtn = document.getElementById('chat-close-btn');
    const chatWidget = document.getElementById('chat-widget');

    if (chatToggleBtn && chatCloseBtn && chatWidget) {
        chatToggleBtn.addEventListener('click', () => {
            chatWidget.classList.add('active');
            chatToggleBtn.classList.add('hidden');
        });

        chatCloseBtn.addEventListener('click', () => {
            chatWidget.classList.remove('active');
            chatToggleBtn.classList.remove('hidden');
        });
    }

    // Chat Message Sending Logic (Frontend Only)
    const chatInputField = document.getElementById('chat-input-field');
    const chatSendBtn = document.getElementById('chat-send-btn');
    const chatMessagesContainer = document.querySelector('.chat-messages');

    if (chatInputField && chatSendBtn && chatMessagesContainer) {
        const sendMessage = () => {
            const text = chatInputField.value.trim();
            if (text !== '') {
                // Create new message element
                const msgDiv = document.createElement('div');
                msgDiv.className = 'message msg-sent';
                msgDiv.textContent = text;
                
                // Append it to the chat container
                chatMessagesContainer.appendChild(msgDiv);
                
                // Clear the input
                chatInputField.value = '';
                
                // Scroll strictly to the new message at the bottom
                chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
            }
        };

        // Send via button click
        chatSendBtn.addEventListener('click', sendMessage);
        
        // Send via Enter key press
        chatInputField.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }

    // FORM HANDLING LOGIC → Real API Integration
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            const originalText = submitBtn.textContent;

            // Visual feedback - Loading State
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';
            submitBtn.style.opacity = '0.7';
            submitBtn.style.pointerEvents = 'none';

            const isRegistration = form.closest('.register-container');
            const isPostProject = window.location.href.includes('post-project');

            let apiUrl = '';
            const formData = new FormData(form);

            if (isRegistration) {
                apiUrl = 'api/register.php';
            } else if (isPostProject) {
                apiUrl = 'api/post_project.php';
            }

            if (apiUrl) {
                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        if (isRegistration) {
                            const name = formData.get('full_name') || formData.get('company_name') || '';
                            localStorage.setItem('currentUser', name);
                            localStorage.setItem('isLoggedIn', 'true');
                            localStorage.setItem('userRole', formData.get('role'));
                        }
                        showNotification(data.message || 'Success!');
                        setTimeout(() => {
                            window.location.href = data.redirect || 'dashboard.html';
                        }, 1000);
                    } else {
                        const errorMsg = data.errors ? data.errors.join(' • ') : (data.detail || data.error || 'Something went wrong.');
                        showNotification('⚠ ' + errorMsg);
                        submitBtn.innerHTML = originalText;
                        submitBtn.style.opacity = '1';
                        submitBtn.style.pointerEvents = 'auto';
                    }
                } catch (err) {
                    showNotification('⚠ ' + err.message);
                    submitBtn.innerHTML = originalText;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.pointerEvents = 'auto';
                }
            } else {
                setTimeout(() => {
                    showNotification('Success!');
                    setTimeout(() => {
                        window.location.href = form.getAttribute('action') || 'dashboard.html';
                    }, 1000);
                }, 1200);
            }
        });
    });

    // ROLE TOGGLE LOGIC (Registration Page – show/hide Client vs Developer fields)
    const roleClientRadio = document.getElementById('role-client');
    const roleDevRadio = document.getElementById('role-dev');
    const devFields = document.querySelector('.dev-fields');
    const clientFields = document.querySelector('.client-fields');

    if (roleClientRadio && roleDevRadio) {
        const toggleRoleFields = () => {
            if (roleDevRadio.checked) {
                if (devFields) devFields.style.display = 'block';
                if (clientFields) clientFields.style.display = 'none';
            } else {
                if (devFields) devFields.style.display = 'none';
                if (clientFields) clientFields.style.display = 'block';
            }
        };
        roleClientRadio.addEventListener('change', toggleRoleFields);
        roleDevRadio.addEventListener('change', toggleRoleFields);
        toggleRoleFields();
    }

    // AUTH FORM HANDLERS
    const registerForm = document.querySelector('form[action="dashboard.html"]');
    if (registerForm && window.location.pathname.includes('register.html')) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('api/register.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        localStorage.setItem('isLoggedIn', 'true');
                        localStorage.setItem('userRole', formData.get('role'));
                        localStorage.setItem('currentUser', formData.get('full_name'));
                        window.location.href = 'dashboard.html';
                    } else {
                        showNotification(res.error || 'Registration failed');
                    }
                }).catch(() => showNotification('Connection error'));
        });
    }

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('api/login.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        localStorage.setItem('isLoggedIn', 'true');
                        localStorage.setItem('userRole', res.role);
                        localStorage.setItem('currentUser', res.name);
                        window.location.href = 'dashboard.html';
                    } else {
                        showNotification(res.error || 'Login failed');
                    }
                }).catch(() => showNotification('Connection error'));
        });
    }

    // GLOBAL NOTIFICATION SYSTEM
    function showNotification(message) {
        const notify = document.createElement('div');
        notify.className = 'card global-notification';
        notify.style.cssText = `
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            padding: 1rem 2rem;
            background: var(--primary);
            color: white;
            z-index: 9999;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 2px solid var(--accent);
        `;
        notify.textContent = message;
        document.body.appendChild(notify);

        // Animate in
        setTimeout(() => {
            notify.style.transform = 'translateX(-50%) translateY(0)';
        }, 10);

        // Remove after delay
        setTimeout(() => {
            notify.style.transform = 'translateX(-50%) translateY(100px)';
            setTimeout(() => notify.remove(), 400);
        }, 3000);
    }

    // NAVIGATION AUTO-HIGHLIGHT
    const navItems = document.querySelectorAll('.nav-item');
    const path = window.location.pathname.split("/").pop();
    
    if (path) {
        navItems.forEach(item => {
            if (item.getAttribute('href') === path) {
                navItems.forEach(link => link.classList.remove('active'));
                item.classList.add('active');
            }
        });
    }

    // SESSION PROTECTION & DYNAMIC PROFILE SYNC
    const protectedPages = ['dashboard.html', 'workspace.html'];
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const userName = localStorage.getItem('currentUser');

    if (protectedPages.includes(path) && !isLoggedIn) {
        // Redirect unauthorized users
        alert('Please sign in to access your dashboard.');
        window.location.href = 'register.html';
    } else if (isLoggedIn && userName) {
        // Find initials
        const parts = userName.split(' ');
        const initials = ((parts[0]?.charAt(0) || '') + (parts[1]?.charAt(0) || '')).toUpperCase() || '??';

        // Sync Sidebar Name
        const sidebarName = document.querySelector('.sidebar h3');
        if (sidebarName) sidebarName.textContent = userName;

        // Sync Nav & Sidebar Avatars
        const avatars = document.querySelectorAll('.dev-avatar');
        avatars.forEach(avatar => {
             // Avoid overwriting tiny developer list avatars if they exist separately
             if (avatar.style.width === '40px' || avatar.style.width === '80px') {
                 avatar.textContent = initials;
             }
        });

        // REPLACE 'SIGN IN' WITH USER AVATAR IN NAVBAR
        const navActions = document.querySelector('.nav-actions');
        const signInBtn = navActions ? navActions.querySelector('a[href="register.html"]') : null;
        
        if (navActions && signInBtn) {
            // Create the avatar element
            const userAvatar = document.createElement('div');
            userAvatar.className = 'dev-avatar';
            userAvatar.textContent = initials;
            userAvatar.style.cssText = `
                width: 40px; 
                height: 40px; 
                border-radius: 50%; 
                border: 2px solid var(--accent); 
                font-size: 1rem; 
                cursor: pointer;
                background: var(--primary);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
            `;
            userAvatar.title = 'Click to Logout';
            
            // Replace the button
            navActions.replaceChild(userAvatar, signInBtn);

            // Add Logout event
            userAvatar.addEventListener('click', () => {
                if (confirm('Do you want to logout?')) {
                    localStorage.clear();
                    window.location.href = 'index.html';
                }
            });
        }
    }

    // POST PROJECT BUTTON GUARD (Client-Only Access)
    const postProjectButtons = document.querySelectorAll('a[href="post-project.html"]');
    postProjectButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
            const userRole = localStorage.getItem('userRole'); // 'Client' or 'Developer'

            if (!isLoggedIn) {
                e.preventDefault();
                showNotification('⚠ Please sign in as a Client to post a project.');
                setTimeout(() => {
                    window.location.href = 'register.html';
                }, 1500);
            } else if (userRole !== 'Client') {
                e.preventDefault();
                showNotification('⚠ Restricted: Only Clients can post projects.');
            }
        });
    });

    // Logout Functionality (Optional but good)
    const navAvatarBtn = document.querySelector('.nav-actions .dev-avatar');
    if (navAvatarBtn && !isLoggedIn) {
        navAvatarBtn.title = 'Click to Logout';
        navAvatarBtn.addEventListener('click', () => {
             if (confirm('Do you want to logout?')) {
                 localStorage.clear();
                 window.location.href = 'index.html';
             }
        });
    }

    // SETTINGS PAGE LOGIC
    if (path === 'settings.html') {
        const setForm = document.getElementById('update-profile-form');
        const delForm = document.getElementById('delete-account-form');

        if (setForm) {
            // Load current profile data
            fetch('api/get_my_profile.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const prof = data.profile;
                        document.getElementById('set-email').value = prof.email;
                        
                        if (prof.role === 'Client') {
                            document.getElementById('settings-client-fields').style.display = 'block';
                            document.getElementById('set-company').value = prof.company_name || '';
                            document.getElementById('set-contact').value = prof.contact_number || '';
                        } else if (prof.role === 'Developer') {
                            document.getElementById('settings-dev-fields').style.display = 'block';
                            document.getElementById('set-fullname').value = prof.full_name || '';
                            document.getElementById('set-level').value = prof.level || 'Trainee';
                            document.getElementById('set-jobtitle').value = prof.job_title || '';
                            document.getElementById('set-rate').value = prof.hourly_rate || '';
                            document.getElementById('set-skills').value = prof.skills || '';
                            document.getElementById('set-github').value = prof.github_url || '';
                            document.getElementById('set-linkedin').value = prof.linkedin_url || '';
                            document.getElementById('set-portfolio').value = prof.portfolio_url || '';
                            document.getElementById('set-bio').value = prof.bio || '';
                        }
                    } else {
                        showNotification('⚠ Failed to load profile: ' + (data.error || 'Unknown'));
                    }
                });

            // Handle Update
            setForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const fd = new FormData(setForm);
                fetch('api/update_profile.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            showNotification('Profile updated successfully!');
                            if (fd.get('full_name')) {
                                localStorage.setItem('currentUser', fd.get('full_name'));
                            }
                        } else {
                            showNotification('⚠ Error: ' + (res.error || 'Update failed'));
                        }
                    });
            });
        }

        if (delForm) {
            // Handle Delete
            delForm.addEventListener('submit', (e) => {
                e.preventDefault();
                if (confirm('Are you absolutely sure you want to permanently delete your account? This action cannot be undone.')) {
                    const fd = new FormData(delForm);
                    fetch('api/delete_account.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                alert('Your account has been successfully deleted.');
                                localStorage.clear();
                                window.location.href = 'index.html';
                            } else {
                                showNotification('⚠ ' + (res.error || 'Failed to delete account'));
                            }
                        });
                }
            });
        }
    }

    // DASHBOARD TAB SWITCHING LOGIC
    const sidebarButtons = document.querySelectorAll('.sidebar-btn');
    const dashboardTabs = document.querySelectorAll('.dashboard-tab');

    if (sidebarButtons.length > 0 && dashboardTabs.length > 0) {
        sidebarButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Update Button Active State
                sidebarButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Hide All Tabs
                dashboardTabs.forEach(tab => tab.style.display = 'none');
                
                // Show Target Tab
                const targetId = btn.getAttribute('data-tab');
                const targetTab = document.getElementById(targetId);
                if (targetTab) {
                    targetTab.style.display = 'block';
                }
            });
        });
    }

    // DYNAMIC PROJECT RENDERING ON DASHBOARD
    const projectListContainer = document.getElementById('project-list-container');
    if (projectListContainer && path === 'dashboard.html') {
        fetch('api/get_projects.php')
            .then(res => res.json())
            .then(data => {
                projectListContainer.innerHTML = ''; // clear loading state
                
                if (data.success && data.projects && data.projects.length > 0) {
                    data.projects.forEach(proj => {
                        let statusClass = 'status-pending';
                        let statusText = proj.status === 'Pending' ? 'Looking for Devs' : proj.status;
                        let actionBtn = `<a href="workspace.html?id=${proj.id}" class="btn btn-outline btn-sm" style="min-width: 140px;">Enter Workspace</a>`;

                        if (proj.status === 'Active') {
                            statusClass = 'status-active';
                            statusText = 'In Progress';
                        } else if (proj.status === 'Completed') {
                            statusClass = 'status-closed';
                            statusText = 'Delivered';
                            actionBtn = `<button class="btn btn-outline btn-sm" disabled style="opacity:0.5; cursor:not-allowed; min-width: 140px;">View Logs</button>`;
                        }

                        // For developers, use their application status if it overrides project status
                        if (!proj.is_client && proj.app_status) {
                            if (proj.app_status === 'Applied' || proj.app_status === 'Pending') {
                                statusText = 'Hire Request Pending';
                                actionBtn = `
                                    <button class="btn btn-primary btn-sm" onclick="respondHire(${proj.id}, 'Accepted')" style="min-width: 80px; padding: 0.5rem 1rem;">Accept</button>
                                    <button class="btn btn-outline btn-sm" onclick="respondHire(${proj.id}, 'Rejected')" style="min-width: 80px; padding: 0.5rem 1rem; color: var(--danger); border-color: var(--danger);">Deny</button>
                                `;
                            } else if (proj.app_status === 'Rejected') {
                                statusClass = 'status-closed';
                                statusText = 'Application Rejected';
                                actionBtn = `<button class="btn btn-outline btn-sm" disabled style="opacity:0.5; cursor:not-allowed; min-width: 140px;">Archived</button>`;
                            }
                        }

                        const item = document.createElement('div');
                        item.className = 'project-list-item';
                        item.innerHTML = `
                            <div style="flex: 1; min-width: 0;">
                                <h3 style="margin-bottom: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90%;">${proj.title}</h3>
                                <p class="text-secondary">${proj.track}</p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 2rem; flex-shrink: 0;">
                                <span class="status-indicator ${statusClass}">
                                    <span class="status-dot"></span> ${statusText}
                                </span>
                                ${actionBtn}
                            </div>
                        `;
                        projectListContainer.appendChild(item);
                    });
                } else {
                    const isClient = localStorage.getItem('userRole') === 'Client';
                    projectListContainer.innerHTML = `
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 2rem; text-align: center;">
                            <h3 class="mb-2">No active projects</h3>
                            <p class="text-secondary mb-4">You haven't ${isClient ? 'posted' : 'joined'} any projects yet.</p>
                            ${isClient ? '<a href="post-project.html" class="btn btn-primary">Post a Project</a>' : '<a href="developers.html" class="btn btn-primary">Browse Projects</a>'}
                        </div>
                    `;
                }
            })
            .catch(err => {
                projectListContainer.innerHTML = `
                    <div style="padding: 2rem; text-align: center; color: var(--error);">
                        ⚠ Failed to load projects. Make sure you are signed in.
                    </div>
                `;
            });
    }

    // DYNAMIC WORKSPACE RENDERING
    if (path === 'workspace.html') {
        const urlParams = new URLSearchParams(window.location.search);
        let projectId = urlParams.get('id');
        
        if (!projectId) {
            // If no project ID is provided, try to find the user's first available project and redirect
            fetch('api/get_projects.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.projects && data.projects.length > 0) {
                        // Find the first "Active" or "Accepted" project
                        let firstActive = data.projects.find(p => p.status === 'Active' || p.app_status === 'Accepted');
                        if (!firstActive) firstActive = data.projects[0]; // fallback to first one
                        
                        window.location.href = `workspace.html?id=${firstActive.id}`;
                    } else {
                        document.querySelector('.main-content').innerHTML = `
                            <div class="card" style="text-align:center; padding:4rem; margin: 4rem auto; max-width: 600px;">
                                <h2 class="text-secondary">No Project Selected</h2>
                                <p class="text-secondary mt-2">Please go to your dashboard and select a project to view its workspace.</p>
                                <a href="dashboard.html" class="btn btn-primary mt-8">Return to Dashboard</a>
                            </div>
                        `;
                    }
                })
                .catch(err => {
                    console.error("Error fetching projects for auto-redirect:", err);
                });
        } else {
            fetch(`api/get_workspace.php?id=${projectId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const proj = data.project;
                        const tasks = data.tasks || [];
                        
                        // Workspace Status Handling for Clients
                        if (localStorage.getItem('userRole') === 'Client' && data.app_status && data.app_status !== 'Accepted') {
                            const mainContent = document.querySelector('.main-content');
                            if (data.app_status === 'No_Hire') {
                                mainContent.innerHTML = `
                                    <div class="card" style="text-align:center; padding:4rem; margin: 4rem auto; max-width: 600px;">
                                        <h2>No Developer Hired Yet</h2>
                                        <p class="text-secondary mt-2">You need to hire a developer to activate this workspace.</p>
                                        <a href="developers.html" class="btn btn-primary mt-4">Go Hire a Developer</a>
                                    </div>
                                `;
                            } else if (data.app_status === 'Applied' || data.app_status === 'Pending') {
                                mainContent.innerHTML = `
                                    <div class="card" style="text-align:center; padding:4rem; margin: 4rem auto; max-width: 600px;">
                                        <h2>Waiting for Developer Response</h2>
                                        <p class="text-secondary mt-2">You have hired <strong>${proj.partner_name}</strong> for this project. Please wait until they accept or deny.</p>
                                        <a href="dashboard.html" class="btn btn-outline mt-4">Return to Dashboard</a>
                                    </div>
                                `;
                            } else if (data.app_status === 'Rejected') {
                                mainContent.innerHTML = `
                                    <div class="card" style="text-align:center; padding:4rem; margin: 4rem auto; max-width: 600px;">
                                        <h2>Hire Request Denied</h2>
                                        <p class="text-secondary mt-2">The developer you requested has denied your invitation.</p>
                                        <a href="developers.html" class="btn btn-primary mt-4">Hire a New Developer</a>
                                    </div>
                                `;
                            }
                            return; // Stop execution, don't load the kanban board
                        }
                        
                        console.log("Loading Workspace Data for project:", proj.title);
                        const titleEl = document.getElementById('ws-project-title-unique');
                        if (titleEl) titleEl.textContent = proj.title;
                        else console.error("Element ws-project-title-unique not found!");
                        document.getElementById('ws-project-level').textContent = proj.required_level + ' Track';
                        
                        let partnerName = proj.partner_name || 'Pending Developer Assignment';
                        let partnerText = proj.status === 'Pending' ? 'Real-time collaboration workspace.' : `Real-time collaboration workspace. Partnered with ${partnerName}.`;
                        document.getElementById('ws-partner-text').textContent = partnerText;
                        
                        // Update Chat Header
                        document.getElementById('ws-chat-name').textContent = partnerName;
                        if (partnerName !== 'Pending Developer Assignment') {
                            document.getElementById('chat-avatar').textContent = partnerName.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
                        } else {
                            document.getElementById('chat-avatar').textContent = '?';
                        }
                        
                        // Render Tasks
                        const cols = {
                            'To Do': document.getElementById('tasks-todo'),
                            'In Progress': document.getElementById('tasks-inprogress'),
                            'Done': document.getElementById('tasks-done')
                        };
                        const counts = { 'To Do': 0, 'In Progress': 0, 'Done': 0 };
                        
                        // Only developers can add tasks
                        console.log("User Role:", localStorage.getItem('userRole'));
                        if (localStorage.getItem('userRole') === 'Developer') {
                            document.getElementById('add-task-container').style.display = 'block';
                        }

                        // Clear columns before rendering
                        Object.values(cols).forEach(c => c.innerHTML = '');
                        
                        tasks.forEach(task => {
                            if (!cols[task.status]) return;
                            counts[task.status]++;
                            
                            const card = document.createElement('div');
                            card.className = 'task-card';
                            
                            let moveBtns = '';
                            let deleteBtn = '';
                            const isDeveloper = localStorage.getItem('userRole') === 'Developer';

                            if (isDeveloper) {
                                deleteBtn = `<button class="btn-delete" onclick="deleteTask(${task.task_id})" title="Delete Task">&times;</button>`;
                                if (task.status === 'To Do') {
                                    moveBtns = `<button class="btn btn-outline btn-sm" onclick="moveTask(${task.task_id}, 'In Progress')" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; margin-top: 0.5rem;">Start &rarr;</button>`;
                                } else if (task.status === 'In Progress') {
                                    card.style = 'border-top: 3px solid var(--warning); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.1);';
                                    moveBtns = `
                                        <div style="display:flex; gap: 0.5rem; margin-top: 0.5rem;">
                                            <button class="btn btn-outline btn-sm" onclick="moveTask(${task.task_id}, 'To Do')" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">&larr; Back</button>
                                            <button class="btn btn-success btn-sm" onclick="moveTask(${task.task_id}, 'Done')" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">Done &rarr;</button>
                                        </div>
                                    `;
                                } else if (task.status === 'Done') {
                                    card.style = 'opacity: 0.6; background-color: rgba(255,255,255,0.01);';
                                    moveBtns = `<button class="btn btn-outline btn-sm" onclick="moveTask(${task.task_id}, 'In Progress')" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; margin-top: 0.5rem;">&larr; Reopen</button>`;
                                }
                            }
                            
                            let titleHtml = task.status === 'Done' ? `<h4 style="text-decoration: line-through; margin-bottom:0.5rem;">${task.title}</h4>` : `<h4 style="margin-bottom:0.5rem;">${task.title}</h4>`;
                            let descHtml = task.description ? `<p class="text-secondary" style="font-size:0.9rem; margin-bottom: 0;">${task.description}</p>` : '';
                            
                            card.innerHTML = `
                                ${deleteBtn}
                                ${titleHtml}
                                ${descHtml}
                                ${moveBtns}
                            `;
                            cols[task.status].appendChild(card);
                        });
                        
                        document.getElementById('count-todo').textContent = counts['To Do'];
                        document.getElementById('count-inprogress').textContent = counts['In Progress'];
                        document.getElementById('count-done').textContent = counts['Done'];
                        
                        // Calculate Sprint Percentage
                        const totalTasks = tasks.length;
                        const doneTasks = counts['Done'];
                        const inProgressTasks = counts['In Progress'];
                        
                        let percentage = 0;
                        if (totalTasks > 0) {
                            percentage = Math.round(((doneTasks + (inProgressTasks * 0.5)) / totalTasks) * 100);
}
                        
                        document.getElementById('ws-progress-text').textContent = percentage + '%';
                        document.getElementById('ws-progress-bar').style.width = percentage + '%';
                        
                        // Workspace Chat Logic
                        const chatInput = document.getElementById('ws-chat-input');
                        const chatSendBtn = document.getElementById('ws-chat-send');
                        const chatContainer = document.getElementById('chat-messages-container');
                        
                        function loadMessages() {
                            fetch(`api/get_messages.php?id=${projectId}`)
                                .then(r => r.json())
                                .then(chatData => {
                                    if (chatData.success) {
                                        chatContainer.innerHTML = '';
                                        if (chatData.messages.length === 0) {
                                            chatContainer.innerHTML = `<div style="text-align:center; color: var(--text-secondary); margin-top: 2rem;">No messages yet. Say hi!</div>`;
                                        } else {
                                            chatData.messages.forEach(msg => {
                                                const div = document.createElement('div');
                                                div.className = msg.is_mine ? 'message msg-sent' : 'message msg-received';
                                                
                                                const nameHtml = msg.is_mine ? '' : `<div style="font-size: 0.75rem; font-weight: 700; margin-bottom: 0.2rem; color: var(--primary);">${msg.sender_name}</div>`;
                                                
                                                // Handle date conversion if it's already a string from CONVERT
                                                let dateObj = msg.sent_at.date ? new Date(msg.sent_at.date) : new Date(msg.sent_at);
                                                const time = isNaN(dateObj.getTime()) ? 'Recently' : dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                                                
                                                div.innerHTML = `
                                                    ${nameHtml}
                                                    <div class="msg-body" style="padding: 0.8rem 1.2rem; border-radius: 20px; max-width: 100%; word-wrap: break-word; background: ${msg.is_mine ? 'var(--primary)' : 'rgba(0,0,0,0.05)'}; color: ${msg.is_mine ? 'white' : 'var(--text-primary)'};">
                                                        ${msg.message_body}
                                                    </div>
                                                    <div style="font-size: 0.65rem; color: var(--text-secondary); margin-top: 0.2rem; text-align: ${msg.is_mine ? 'right' : 'left'};">${time}</div>
                                                `;
                                                
                                                div.style.marginBottom = '1rem';
                                                div.style.display = 'flex';
                                                div.style.flexDirection = 'column';
                                                div.style.alignItems = msg.is_mine ? 'flex-end' : 'flex-start';
                                                div.style.maxWidth = '85%';
                                                div.style.alignSelf = msg.is_mine ? 'flex-end' : 'flex-start';

                                                chatContainer.appendChild(div);
                                            });
                                            chatContainer.scrollTop = chatContainer.scrollHeight;
                                        }
                                    }
                                });
                        }
                        
                        loadMessages();
                        setInterval(loadMessages, 5000); // Poll for new messages every 5 seconds
                        
                        function sendMessage() {
                            const text = chatInput.value.trim();
                            if (!text) return;
                            
                            console.log("Sending message to PID:", projectId, "Text:", text);
                            const fd = new FormData();
                            fd.append('project_id', projectId);
                            fd.append('message', text);
                            
                            fetch('api/send_message.php', { method: 'POST', body: fd })
                                .then(r => r.json())
                                .then(sendData => {
                                    console.log("Send Result:", sendData);
                                    if (sendData.success) {
                                        chatInput.value = '';
                                        loadMessages();
                                    } else {
                                        alert("Error sending: " + (sendData.error || 'Unknown error'));
                                    }
                                });
                        }
                        
                        if (chatSendBtn) chatSendBtn.addEventListener('click', sendMessage);
                        if (chatInput) {
                            chatInput.addEventListener('keypress', (e) => {
                                if (e.key === 'Enter') sendMessage();
                            });
                        }
                    } else {
                        // Access Denied
                        document.querySelector('.main-content').innerHTML = `
                            <div class="card" style="text-align:center; padding:4rem; margin: 4rem auto; max-width: 600px;">
                                <h2 class="text-secondary">Access Denied</h2>
                                <p class="text-secondary mt-2">You do not have permission to view this workspace or it doesn't exist.</p>
                                <a href="dashboard.html" class="btn btn-primary mt-8">Return to Dashboard</a>
                            </div>
                        `;
                    }
                })
                .catch(err => console.error(err));
        }
    }

    // DYNAMIC PROFILE RENDERING
    if (path === 'profile.html') {
        const urlParams = new URLSearchParams(window.location.search);
        const devId = urlParams.get('id');
        if (devId) {
            fetch(`api/get_profile.php?id=${devId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const dev = data.developer;
                        document.getElementById('prof-name').textContent = dev.full_name;
                        document.getElementById('prof-avatar').textContent = dev.full_name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
                        
                        let badgeClass = 'badge-junior';
                        if (dev.level === 'Mid') badgeClass = 'badge-mid';
                        if (dev.level === 'Senior') badgeClass = 'badge-senior';
                        if (dev.level === 'Trainee') badgeClass = 'badge-trainee';
                        
                        const profLevel = document.getElementById('prof-level');
                        profLevel.className = `level-badge ${badgeClass}`;
                        profLevel.textContent = `${dev.level} Developer`;
                        
                        document.getElementById('prof-title').textContent = dev.job_title || 'Software Engineer';
                        document.getElementById('prof-desc').textContent = `Professional developer charging $${dev.hourly_rate}/hr. Contact: ${dev.email}`;
                        
                        const linksContainer = document.getElementById('prof-links');
                        linksContainer.innerHTML = '';
                        if (dev.portfolio_url) linksContainer.innerHTML += `<a href="${dev.portfolio_url}" target="_blank" class="btn btn-outline btn-sm">Portfolio</a>`;
                        if (dev.github_url) linksContainer.innerHTML += `<a href="${dev.github_url}" target="_blank" class="btn btn-outline btn-sm">GitHub</a>`;
                        if (dev.linkedin_url) linksContainer.innerHTML += `<a href="${dev.linkedin_url}" target="_blank" class="btn btn-outline btn-sm">LinkedIn</a>`;
                        
                        const skillsContainer = document.getElementById('prof-skills');
                        skillsContainer.innerHTML = '';
                        if (dev.skills && dev.skills.length > 0) {
                            dev.skills.forEach(skill => {
                                skillsContainer.innerHTML += `<span class="skill-tag">${skill}</span>`;
                            });
                        } else {
                            skillsContainer.innerHTML = `<span class="text-secondary">No specific skills listed.</span>`;
                        }

                        // Dynamic Profile Sections
                        document.getElementById('dyn-about-title').textContent = `About ${dev.full_name.split(' ')[0]}`;
                        const bioEl = document.getElementById('dyn-bio');
                        
                        const defaultBio = `${dev.full_name} is a dedicated ${dev.level} ${dev.job_title || 'Developer'} focused on delivering high-quality web solutions.`;
                        bioEl.textContent = dev.bio || defaultBio;

                        // Experience Widget Logic
                        const expYearsEl = document.getElementById('dyn-exp-years');
                        const expDescEl = document.getElementById('dyn-exp-desc');
                        
                        let years = "0-1";
                        let desc = `${dev.full_name} is currently starting their professional journey, building a strong foundation in modern technologies.`;
                        
                        if (dev.level === 'Junior') {
                            years = "1-3";
                            desc = `${dev.full_name} has established core professional experience and has contributed to multiple production-level projects.`;
                        } else if (dev.level === 'Mid') {
                            years = "3-5";
                            desc = `${dev.full_name} is a seasoned professional with deep technical expertise and a proven track record of architecting scalable systems.`;
                        } else if (dev.level === 'Senior') {
                            years = "5+";
                            desc = `${dev.full_name} is a veteran engineer with extensive experience leading teams and delivering complex, enterprise-grade solutions.`;
                        }
                        
                        expYearsEl.textContent = `${years} Years`;
                        expDescEl.textContent = desc;

                        // GitHub Auto-Sync for Bio only
                        if (!dev.bio && dev.github_url) {
                            try {
                                const parts = dev.github_url.replace(/\/$/, '').split('/');
                                const ghUser = parts.pop();
                                
                                if (ghUser) {
                                    bioEl.textContent = "Syncing with GitHub...";
                                    fetch(`https://api.github.com/users/${ghUser}`)
                                        .then(r => r.json())
                                        .then(ghData => {
                                            if (ghData.bio) {
                                                bioEl.textContent = ghData.bio;
                                            } else {
                                                bioEl.textContent = defaultBio;
                                            }
                                        })
                                        .catch(() => {
                                            bioEl.textContent = defaultBio;
                                        });
                                }
                            } catch (e) { 
                                bioEl.textContent = defaultBio;
                            }
                        }
                        
                        // Hire Action
                        if (data.client_projects && data.client_projects.length > 0) {
                            const actionContainer = document.getElementById('hire-action-container');
                            actionContainer.style.display = 'block';
                            
                            const select = document.getElementById('hire-project-select');
                            data.client_projects.forEach(p => {
                                select.innerHTML += `<option value="${p.project_id}">${p.title}</option>`;
                            });
                            
                            document.getElementById('btn-hire').addEventListener('click', () => {
                                const projId = select.value;
                                if (!projId) {
                                    alert("Please select a project first.");
                                    return;
                                }
                                
                                const formData = new FormData();
                                formData.append('dev_id', devId);
                                formData.append('project_id', projId);
                                
                                fetch('api/hire_developer.php', { method: 'POST', body: formData })
                                    .then(r => r.json())
                                    .then(res => {
                                        const statusObj = document.getElementById('hire-status');
                                        if (res.success) {
                                            statusObj.textContent = "Hire Request Sent!";
                                            statusObj.style.color = "var(--success)";
                                            document.getElementById('btn-hire').disabled = true;
                                        } else {
                                            statusObj.textContent = res.error;
                                            statusObj.style.color = "var(--danger)";
                                        }
                                    });
                            });
                        }
                    } else {
                        document.querySelector('.profile-hero').innerHTML = `<h2 class="text-secondary">Developer not found.</h2>`;
                    }
                });
        }
    }

    // DYNAMIC DEVELOPERS LIST
    if (path === 'developers.html') {
        fetch('api/get_developers.php')
            .then(r => r.json())
            .then(data => {
                const grid = document.getElementById('dev-grid');
                if (data.success && data.developers.length > 0) {
                    grid.innerHTML = '';
                    data.developers.forEach(dev => {
                        let badgeClass = 'badge-junior';
                        let devClass = 'junior';
                        if (dev.level === 'Mid') { badgeClass = 'badge-mid'; devClass = 'mid'; }
                        if (dev.level === 'Senior') { badgeClass = 'badge-senior'; devClass = 'senior'; }
                        if (dev.level === 'Trainee') { badgeClass = 'badge-trainee'; devClass = 'trainee'; }
                        
                        let skillsHtml = '';
                        if (dev.skills && dev.skills.length > 0) {
                            dev.skills.slice(0, 3).forEach(s => {
                                skillsHtml += `<span class="skill-tag">${s}</span>`;
                            });
                        }
                        
                        grid.innerHTML += `
                            <div class="card dev-card ${devClass}">
                                <div class="dev-header">
                                    <div class="dev-avatar">${dev.full_name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase()}</div>
                                    <div class="dev-info">
                                        <h3>${dev.full_name}</h3>
                                        <span class="level-badge ${badgeClass}">${dev.level} Level</span>
                                    </div>
                                </div>
                                <div class="skills-container">${skillsHtml}</div>
                                <p class="text-secondary" style="flex:1;">Available for $${dev.hourly_rate}/hr.</p>
                                <a href="profile.html?id=${dev.dev_id}" class="btn btn-outline btn-block mt-4">View Profile & Collaborate</a>
                            </div>
                        `;
                    });
                } else {
                    grid.innerHTML = `<div style="text-align: center; grid-column: 1 / -1; padding: 2rem;">No developers registered yet.</div>`;
                }
            });
    }
});

// Global functions for developer accepting/denying hire requests
window.respondHire = function(projectId, status) {
    if (!confirm(`Are you sure you want to ${status === 'Accepted' ? 'ACCEPT' : 'DENY'} this hire request?`)) return;
    
    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('status', status);
    
    fetch('api/respond_hire.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                window.location.reload();
            } else {
                alert(res.error || 'Failed to update request.');
            }
        });
};

    window.moveTask = function(id, status) {
        if (localStorage.getItem('userRole') !== 'Developer') return;
        const formData = new FormData();
        formData.set('task_id', id);
        formData.set('status', status);

        fetch(`api/move_task.php`, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else showNotification(data.error || 'Failed to move task');
            });
    };

    window.deleteTask = function(id) {
        if (localStorage.getItem('userRole') !== 'Developer') return;
        if (!confirm("Are you sure you want to delete this task?")) return;
        
        const formData = new FormData();
        formData.set('id', id);

        fetch(`api/delete_task.php`, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else showNotification(data.error || 'Failed to delete task');
            });
    };

window.addTask = function() {
    if (localStorage.getItem('userRole') !== 'Developer') return;
    const title = prompt("Enter new task title:");
    if (!title || !title.trim()) return;
    
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('id');
    
    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('title', title.trim());
    
    fetch('api/add_task.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) window.location.reload();
            else alert(res.error || 'Failed to add task');
        });
};

