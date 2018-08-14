/*
 * Copyright (c) 2017 - 2018, Tim Düsterhus
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

#include<errno.h>
#include<stdio.h>
#include<stdlib.h>
#include<string.h>
#include<unistd.h>
#include<arpa/inet.h>
#include<sys/socket.h>
#include<sys/types.h>
#include<sys/wait.h>

void child(char * verp_php, int client_fd)
{
	printf("File descriptor is %d\n", client_fd);
	dup2(client_fd, 0);
	dup2(client_fd, 1);
	dup2(client_fd, 2);
	for (int i = 3; i <= client_fd; i++) {
		close(i);
	}

	char * argv[] = { "php", verp_php, (char *) NULL };

	execvp("php", argv);
}

int main(int argc, char ** argv)
{
	setbuf(stdout, NULL);

	if (argc != 4) {
		fprintf(stderr, "%s <host> <port> <verp.php>\n", argv[0]);
		return 1;
	}

	int sock_fd;

	{
		char * host = argv[1];
		char * endptr;
		long int port = strtol(argv[2], &endptr, 10);
		if (*endptr) {
			fprintf(stderr, "Port is not a valid number: %s\n", argv[2]);
			return 1;
		}

		{
			struct sockaddr_storage addr;
			int socket_type;

			memset(&addr, 0, sizeof(addr));

			if (inet_pton(AF_INET, host, &((struct sockaddr_in *) &addr)->sin_addr)) {
				printf("Detected IPv4 address: %s\n", host);
				socket_type = PF_INET;
				((struct sockaddr_in *) &addr)->sin_port = htons(port);
				((struct sockaddr_in *) &addr)->sin_family = AF_INET;
			}
			else if (inet_pton(AF_INET6, host, &((struct sockaddr_in6 *) &addr)->sin6_addr)) {
				printf("Detected IPv6 address: %s\n", host);
				socket_type = PF_INET6;
				((struct sockaddr_in6 *) &addr)->sin6_port = htons(port);
				((struct sockaddr_in6 *) &addr)->sin6_family = AF_INET6;
			}
			else {
				fprintf(stderr, "Could not parse host: %s\n", host);
				return 1;
			}

			if ((sock_fd = socket(socket_type, SOCK_STREAM | SOCK_NONBLOCK, 0)) == -1) {
				perror("socket");
				return 1;
			}

			if (bind(sock_fd, (struct sockaddr *) &addr, sizeof(addr)) == -1) {
				perror("bind");
				goto fail;
			}

			if (listen(sock_fd, 5) == -1) {
				perror("listen");
				goto fail;
			}
		}
	}

	while (1) {
		int client_fd;
		{
			struct sockaddr_storage client_addr;
			memset(&client_addr, 0, sizeof(client_addr));
			socklen_t client_addr_size = 0;
			if ((client_fd = accept(sock_fd, (struct sockaddr *) &client_addr, &client_addr_size)) == -1) {
				if (errno == EINTR) continue;
				if (errno == EAGAIN || errno == EWOULDBLOCK) {
					pid_t wpid;
					int status;
					while ((wpid = waitpid(-1, &status, WNOHANG)) > 0) {
						printf("Child %d exited\n", wpid);
					}
					sleep(1);
					continue;
				}
				perror("accept");
				goto fail;
			}
		}

		{
			pid_t pid;
			switch (pid = fork()) {
				case -1:
					perror("fork");
					goto fail;
				case 0:
					child(argv[3], client_fd);
					fprintf(stderr, "unreachable\n");
					abort();
			}
			printf("Forked off child %d\n", pid);
		}
		close(client_fd);
	}

fail:
	close(sock_fd);
	return 1;
}
